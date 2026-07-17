<?php

declare(strict_types=1);

namespace App\Actions\Mentoring;

use App\Enums\EntitlementFeature;
use App\Jobs\SyncMentorZoom;
use App\Models\MentorSession;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Messaging\Messenger;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a session (PRD §6.11). Students are held to the 4-hour notice
 * rule; mentors/staff can cancel any time. Every cancellation refunds the
 * credit — the student only ever loses one by not showing up. Zoom cleanup
 * is queued; both sides are told immediately.
 */
final readonly class CancelMentorSession
{
    public function __construct(
        private EntitlementService $entitlements,
        private Messenger $messenger,
    ) {}

    public function handle(User $actor, MentorSession $session, bool $isStudent): MentorSession
    {
        if ($session->status !== MentorSession::STATUS_BOOKED) {
            throw ValidationException::withMessages(['session' => 'This session is no longer active.']);
        }

        if ($isStudent && ! $session->withinNoticeWindow()) {
            throw ValidationException::withMessages([
                'session' => 'Sessions can be cancelled up to '.config('mentoring.cancel_notice_hours', 4).' hours before the start.',
            ]);
        }

        $session->update([
            'status' => MentorSession::STATUS_CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ]);

        if ($session->student !== null) {
            $this->entitlements->grantCredits(
                $session->student,
                EntitlementFeature::Mentor->value,
                1,
                "mentor_refund:cancel:{$session->id}",
            );
        }

        if ($session->zoom_meeting_id !== null) {
            SyncMentorZoom::dispatch($session->id, 'delete');
        }

        $this->notify($session, 'mentor_cancelled');

        return $session->refresh();
    }

    private function notify(MentorSession $session, string $template): void
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
                $this->messenger->send($recipient, $template, [
                    'name' => $recipient->name,
                    'with' => $with,
                    'when' => $when,
                ]);
            }
        }
    }
}
