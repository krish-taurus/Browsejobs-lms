<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Fees\LiftAccessBlocks;
use App\Enums\InstalmentStatus;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Instant fee unblock (PRD §6.8 "payment captured → instant auto-unblock").
 * Dispatched from MarkInstalmentPaid on capture; lifts the student's active
 * access blocks once they have no remaining overdue instalment. Idempotent,
 * tenant-re-entered like FlipEnrolment.
 */
final class LiftFeeBlocks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $feePlanId) {}

    public function handle(LiftAccessBlocks $lift): void
    {
        $plan = FeePlan::query()->withoutGlobalScopes()->with('student')->find($this->feePlanId);
        if ($plan === null || $plan->student === null) {
            return;
        }

        $tenant = Tenant::query()->find($plan->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($plan, $lift): void {
            $student = $plan->student;

            $planIds = FeePlan::query()->where('user_id', $student->id)->pluck('id');
            $hasOverdue = Instalment::query()
                ->whereIn('fee_plan_id', $planIds)
                ->where('status', '!=', InstalmentStatus::Paid->value)
                ->whereDate('due_on', '<', Carbon::today()->toDateString())
                ->exists();

            if (! $hasOverdue) {
                $lift->handle($student);
            }
        });
    }
}
