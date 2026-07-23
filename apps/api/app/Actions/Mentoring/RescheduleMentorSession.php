<?php

declare(strict_types=1);

namespace App\Actions\Mentoring;

use App\Jobs\SendMentorReminder;
use App\Jobs\SyncMentorZoom;
use App\Models\MentorSession;
use App\Support\Mentoring\SlotFinder;
use App\Support\Messaging\Messenger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a session to a new slot (PRD §6.11, 4-hour notice on the old time).
 * The credit stays spent — a reschedule is not a cancel-and-rebook. Old
 * reminders die on their start-time guard; fresh ones are armed for the new
 * time. 1:1s are direct-connect (ADR 0043) — only legacy sessions that still
 * carry a Zoom meeting get it moved in place.
 */
final readonly class RescheduleMentorSession
{
    public function __construct(
        private SlotFinder $slots,
        private Messenger $messenger,
    ) {}

    public function handle(MentorSession $session, CarbonImmutable $newStart): MentorSession
    {
        if ($session->status !== MentorSession::STATUS_BOOKED) {
            throw ValidationException::withMessages(['session' => 'This session is no longer active.']);
        }

        if (! $session->withinNoticeWindow()) {
            throw ValidationException::withMessages([
                'session' => 'Sessions can be moved up to '.config('mentoring.cancel_notice_hours', 4).' hours before the start.',
            ]);
        }

        $mentor = $session->mentor()->firstOrFail();

        if (! $this->slots->isBookable($mentor, $newStart)) {
            throw ValidationException::withMessages(['starts_at' => 'That slot is not available.']);
        }

        DB::transaction(function () use ($session, $newStart): void {
            $taken = MentorSession::query()
                ->where('mentor_profile_id', $session->mentor_profile_id)
                ->where('status', MentorSession::STATUS_BOOKED)
                ->where('starts_at', $newStart)
                ->where('id', '!=', $session->id)
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['starts_at' => 'That slot was just booked by someone else.']);
            }

            $session->update([
                'starts_at' => $newStart,
                'reminded_24h_at' => null,
                'reminded_1h_at' => null,
            ]);
        });

        // New 1:1s are direct-connect with no meeting (ADR 0043); this only
        // maintains sessions booked before that change.
        if ($session->zoom_meeting_id !== null) {
            SyncMentorZoom::dispatch($session->id, 'update');
        }

        foreach ([['24h', 24 * 60], ['1h', 60]] as [$phase, $minutes]) {
            $at = $newStart->subMinutes($minutes);
            if ($at->isFuture()) {
                SendMentorReminder::dispatch($session->id, $phase, $newStart->timestamp)->delay($at);
            }
        }

        $this->notify($session->refresh());

        return $session;
    }

    private function notify(MentorSession $session): void
    {
        $session->loadMissing(['mentor.user', 'student']);
        $when = $session->starts_at->copy()
            ->setTimezone((string) config('mentoring.timezone', 'Asia/Kolkata'))
            ->format('D, j M · g:i A').' IST';

        foreach ([
            [$session->student, (string) $session->mentor?->user?->name],
            [$session->mentor?->user, (string) $session->student?->name],
        ] as [$recipient, $with]) {
            if ($recipient !== null) {
                $this->messenger->send($recipient, 'mentor_rescheduled', [
                    'name' => $recipient->name,
                    'with' => $with,
                    'when' => $when,
                    'link' => rtrim((string) config('app.frontend_url', ''), '/').'/mentors',
                ]);
            }
        }
    }
}
