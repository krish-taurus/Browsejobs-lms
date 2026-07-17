<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Reports\GenerateTrainerBrief;
use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers a trainer's AI pre-class brief (PRD §6.10), armed ~30 min before a session
 * on the reminder rail. Token-guarded exactly like SendSessionReminder: a stale token
 * (reschedule/cancel) is a no-op.
 */
final class SendTrainerBrief implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $liveSessionId,
        public readonly string $token,
    ) {}

    public function handle(GenerateTrainerBrief $action): void
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

        app(TenantContext::class)->run($tenant, function () use ($session, $action): void {
            try {
                $action->handle($session);
            } catch (Throwable) {
                // Never let a brief failure surface on the reminder rail.
            }
        });
    }
}
