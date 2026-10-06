<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\FeePlan;
use App\Models\User;

/**
 * The real fee gate (P2.3, PRD §6.8). Denies live-class / recordings access when
 * the student has an active access block (soft or hard), or when they are still
 * Payment Pending in a PAID batch beyond the grace period.
 *
 * That second rule matters because the funnel marks a whole cohort Payment
 * Pending on conversion without raising fee plans, so dunning — and therefore
 * access blocks — never start. Without it an unpaid student keeps full access to
 * a paid batch indefinitely. They get `fees.ladder.grace_days` from the moment
 * they were converted, which the CRM can raise.
 */
final class DuesFeeGate implements FeeGate
{
    public function allowsLiveAccess(User $student, Batch $batch): bool
    {
        // A granted extension outranks everything: the academic team has
        // explicitly bought this student more time.
        if ($this->hasActiveExtension($student, $batch)) {
            return true;
        }

        $blocked = AccessBlock::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $student->tenant_id)
            ->where('user_id', $student->id)
            ->whereNull('lifted_at')
            ->exists();

        if ($blocked) {
            return false;
        }

        return $this->withinPaymentGrace($student, $batch);
    }

    /** Whether this student has an unexpired access extension on this batch. */
    private function hasActiveExtension(User $student, Batch $batch): bool
    {
        $plan = FeePlan::query()->withoutGlobalScopes()
            ->where('user_id', $student->id)
            ->where('batch_id', $batch->id)
            ->whereNotNull('access_extended_until')
            ->latest('id')
            ->first();

        return $plan?->accessExtensionActive() ?? false;
    }

    /**
     * A Payment Pending seat in a paid batch keeps access for the grace period
     * only. Masterclass and bootcamp stages are free, so they never gate here.
     */
    private function withinPaymentGrace(User $student, Batch $batch): bool
    {
        if ($batch->type !== BatchType::Paid) {
            return true;
        }

        $member = $batch->members()
            ->where('user_id', $student->id)
            ->first();

        if ($member === null || $member->status !== BatchMemberStatus::PaymentPending) {
            return true;
        }

        $since = $member->updated_at ?? $member->created_at;

        if ($since === null) {
            return true;
        }

        $graceDays = (int) config('fees.ladder.grace_days', 5);

        return now()->lessThanOrEqualTo($since->copy()->addDays($graceDays));
    }
}
