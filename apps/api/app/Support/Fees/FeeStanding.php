<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\FeePlan;
use App\Models\Instalment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a student really owes. One rule, used by the daily dunning ladder, the
 * instant unblock on payment and the midnight reconciliation, so they can never
 * disagree about whether someone is overdue.
 *
 * A plan is SUPERSEDED when the student moved to another batch of the same
 * course and paid there: the old plan is an abandoned seat with nothing paid on
 * it, and must not block someone who has paid for the seat they actually took.
 * Cancelled plans owe nothing either.
 */
final class FeeStanding
{
    /**
     * Ids of the student's active plans that a later paid plan for the same
     * course has replaced.
     *
     * @return Collection<int, int>
     */
    public function supersededPlanIds(int $userId): Collection
    {
        $plans = FeePlan::query()
            ->where('user_id', $userId)
            ->where('status', '!=', FeePlanStatus::Cancelled->value)
            ->with('batch:id,course_id')
            ->withCount(['instalments as paid_instalments_count' => fn ($q) => $q->where('status', InstalmentStatus::Paid->value)])
            ->orderBy('id')
            ->get();

        return $plans
            ->filter(function (FeePlan $old) use ($plans): bool {
                $courseId = $old->batch?->course_id;
                if ($old->status !== FeePlanStatus::Active || (int) $old->paid_instalments_count > 0 || $courseId === null) {
                    return false;
                }

                return $plans->contains(fn (FeePlan $new): bool => $new->id > $old->id
                    && $new->batch_id !== $old->batch_id
                    && (int) $new->paid_instalments_count > 0
                    && $new->batch?->course_id === $courseId);
            })
            ->pluck('id')
            ->values();
    }

    /**
     * Unpaid instalments past their due date that the student really owes.
     *
     * @return Collection<int, Instalment>
     */
    public function overdueInstalments(int $userId, ?Carbon $today = null): Collection
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $superseded = $this->supersededPlanIds($userId);

        $planIds = FeePlan::query()
            ->where('user_id', $userId)
            ->where('status', '!=', FeePlanStatus::Cancelled->value)
            ->pluck('id')
            ->diff($superseded);

        return Instalment::query()
            ->whereIn('fee_plan_id', $planIds)
            ->where('status', '!=', InstalmentStatus::Paid->value)
            ->whereDate('due_on', '<', $today->toDateString())
            ->get();
    }

    public function hasOverdue(int $userId, ?Carbon $today = null): bool
    {
        return $this->overdueInstalments($userId, $today)->isNotEmpty();
    }

    public function isSuperseded(FeePlan $plan): bool
    {
        return $this->supersededPlanIds((int) $plan->user_id)->contains($plan->id);
    }
}
