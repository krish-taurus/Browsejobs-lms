<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\AccessBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fees\FeeStanding;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * The midnight cross-check (`fees:reconcile-blocks`). Walks every active fee
 * block and lifts the ones whose student no longer owes anything overdue: paid
 * on another plan or another batch, plan cancelled, or the payment landed while
 * the instant unblock was not running. Blocks that are still earned stay.
 *
 * Idempotent and safe to run at any time. Returns the students it unblocked.
 *
 * @phpstan-type Reconciled array{checked: int, lifted: int, kept: int}
 */
final readonly class ReconcileFeeBlocks
{
    public function __construct(
        private FeeStanding $standing,
        private LiftAccessBlocks $lift,
    ) {}

    /**
     * @return Reconciled
     */
    public function handle(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $summary = ['checked' => 0, 'lifted' => 0, 'kept' => 0];

        $tenantIds = AccessBlock::query()->withoutGlobalScopes()
            ->whereNull('lifted_at')
            ->whereNotNull('fee_plan_id')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($today, &$summary): void {
                $userIds = AccessBlock::query()
                    ->whereNull('lifted_at')
                    ->whereNotNull('fee_plan_id')
                    ->distinct()
                    ->pluck('user_id');

                foreach ($userIds as $userId) {
                    $student = User::query()->find($userId);
                    if ($student === null) {
                        continue;
                    }

                    $summary['checked']++;
                    if ($this->standing->hasOverdue((int) $userId, $today)) {
                        $summary['kept']++;

                        continue;
                    }

                    $this->lift->handle($student, 'Reconciled: nothing overdue');
                    $summary['lifted']++;
                }
            });
        }

        return $summary;
    }
}
