<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MentorSession;
use App\Models\Tenant;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One reminder phase (T-24h or T-1h) for a mentor session. Self-guarding
 * and idempotent (sync-driver safe): it only fires inside its window, only
 * for a still-booked session whose start time matches what was armed, and
 * only once per phase.
 */
final class SendMentorReminder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $phase,
        public readonly int $expectedStartTimestamp,
    ) {}

    public function handle(Messenger $messenger): void
    {
        $session = MentorSession::query()->withoutGlobalScopes()
            ->with(['mentor.user', 'student'])
            ->find($this->sessionId);

        if (
            $session === null
            || $session->status !== MentorSession::STATUS_BOOKED
            || $session->starts_at->timestamp !== $this->expectedStartTimestamp
            || $session->starts_at->isPast()
        ) {
            return;
        }

        $column = $this->phase === '24h' ? 'reminded_24h_at' : 'reminded_1h_at';
        $windowMinutes = $this->phase === '24h' ? 24 * 60 : 60;

        // Not yet inside the phase window (a sync driver runs this at
        // dispatch time) or already sent → do nothing, don't mark.
        if ($session->{$column} !== null || now()->addMinutes($windowMinutes)->lt($session->starts_at)) {
            return;
        }

        $tenant = Tenant::query()->find($session->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($session, $messenger, $column): void {
            $when = $session->starts_at->copy()
                ->setTimezone((string) config('mentoring.timezone', 'Asia/Kolkata'))
                ->format('D, j M · g:i A').' IST';

            foreach ([
                [$session->student, (string) $session->mentor?->user?->name],
                [$session->mentor?->user, (string) $session->student?->name],
            ] as [$recipient, $with]) {
                if ($recipient !== null) {
                    $messenger->send($recipient, 'mentor_reminder', [
                        'name' => $recipient->name,
                        'with' => $with,
                        'when' => $when,
                        'link' => (string) $session->join_url,
                    ]);
                }
            }

            $session->update([$column => now()]);
        });
    }
}
