<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InAppNotification;
use App\Models\MentorSession;
use App\Models\Tenant;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Post-booking pipeline (PRD §6.11): Zoom auto-create → instant both-side
 * notifications (WhatsApp/email via Messenger + dashboard cards; the email
 * carries the .ics link) → arm the T-24h and T-1h reminders. Queued —
 * booking answers instantly, Zoom latency never blocks the request.
 */
final class FinalizeMentorBooking implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $sessionId) {}

    public function handle(ZoomClient $zoom, Messenger $messenger): void
    {
        $session = MentorSession::query()->withoutGlobalScopes()
            ->with(['mentor.user', 'student'])
            ->find($this->sessionId);
        if ($session === null || $session->status !== MentorSession::STATUS_BOOKED) {
            return;
        }

        $tenant = Tenant::query()->find($session->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($session, $zoom, $messenger): void {
            $student = $session->student;
            $mentorUser = $session->mentor?->user;
            $label = $session->purpose === MentorSession::PURPOSE_PLACEMENT ? 'Placement interview' : 'Mentor session';

            if ($session->zoom_meeting_id === null) {
                $meeting = $zoom->createMeeting(
                    "{$label}: {$student?->name} × {$mentorUser?->name}",
                    $session->starts_at->toImmutable(),
                    $session->duration_minutes,
                );

                $session->update([
                    'zoom_meeting_id' => $meeting->id,
                    'join_url' => $meeting->joinUrl,
                    'start_url' => $meeting->startUrl,
                ]);
            }

            $when = $session->starts_at->copy()
                ->setTimezone((string) config('mentoring.timezone', 'Asia/Kolkata'))
                ->format('D, j M · g:i A').' IST';
            $frontend = rtrim((string) config('app.frontend_url', ''), '/');

            if ($student !== null) {
                $messenger->send($student, 'mentor_booked', [
                    'name' => $student->name,
                    'with' => (string) $mentorUser?->name,
                    'when' => $when,
                    'link' => "{$frontend}/mentors",
                ]);

                InAppNotification::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'user_id' => $student->id,
                    'title' => "{$label} booked — {$when}",
                    'body' => "With {$mentorUser?->name}. The join link and calendar invite are on your Mentors page.",
                    'url' => '/mentors',
                ]);
            }

            if ($mentorUser !== null) {
                $messenger->send($mentorUser, 'mentor_booked', [
                    'name' => $mentorUser->name,
                    'with' => (string) $student?->name,
                    'when' => $when,
                    'link' => "{$frontend}/admin/mentoring",
                ]);

                InAppNotification::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'user_id' => $mentorUser->id,
                    'title' => "New {$label} — {$when}",
                    'body' => "With {$student?->name}.",
                    'url' => '/admin/mentoring',
                ]);
            }

            // T-24h / T-1h reminder ladder; each phase self-guards, so a
            // rescheduled or cancelled session never gets a stale reminder.
            foreach ([['24h', 24 * 60], ['1h', 60]] as [$phase, $minutes]) {
                $at = $session->starts_at->copy()->subMinutes($minutes);
                if ($at->isFuture()) {
                    SendMentorReminder::dispatch($session->id, $phase, $session->starts_at->timestamp)->delay($at);
                }
            }
        });
    }
}
