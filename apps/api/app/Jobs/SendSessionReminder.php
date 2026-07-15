<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BatchMemberStatus;
use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Support\MagicLink\MagicLinkService;
use App\Support\Notifications\SessionNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers one rung of the reminder ladder. No-ops if the session was cancelled
 * or rescheduled (its reminder_token no longer matches this job's token). Sends
 * every active member a magic dashboard join link — never a raw Zoom URL.
 */
final class SendSessionReminder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $liveSessionId,
        public readonly string $token,
        public readonly string $window,
    ) {}

    public function handle(SessionNotifier $notifier, MagicLinkService $links): void
    {
        $session = LiveSession::query()->withoutGlobalScopes()->find($this->liveSessionId);

        if ($session === null
            || $session->status !== LiveSessionStatus::Scheduled
            || $session->reminder_token !== $this->token) {
            return;
        }

        $tenant = Tenant::query()->find($session->tenant_id);
        if ($tenant === null) {
            return;
        }

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $members = $session->batch->members()->whereIn('status', $occupying)->with('student')->get();

        foreach ($members as $member) {
            $student = $member->student;

            $magicUrl = $links->create(
                $tenant,
                'join-class',
                $student,
                ['redirect' => "/classes/{$session->id}/join", 'session_id' => $session->id],
            );

            $notifier->reminder($student, $session, $magicUrl, $this->window);
        }
    }
}
