<?php

declare(strict_types=1);

namespace App\Support\Fees;

use App\Enums\InstalmentStatus;
use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\Instalment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes a fee plan's dunning state (PRD §6.8). The earliest unpaid instalment
 * is the ladder anchor; days-to-due drives reminders and blocks. Kept free of
 * side effects so it can back the command, the student endpoint, and the admin
 * dashboard identically.
 */
final class DunningStatus
{
    public function for(FeePlan $plan, ?Carbon $today = null): DunningSnapshot
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $instalments = $plan->relationLoaded('instalments')
            ? $plan->instalments
            : $plan->instalments()->orderBy('seq')->get();

        $unpaid = $instalments
            ->filter(fn (Instalment $i) => $i->status !== InstalmentStatus::Paid)
            ->sortBy('seq');

        $next = $unpaid->first();
        $outstanding = (int) $unpaid->sum('amount_paise');

        $daysToDue = 0;
        $overdue = false;
        if ($next !== null) {
            $due = $next->due_on->copy()->startOfDay();
            $daysToDue = (int) $today->diffInDays($due, false); // >0 future, <0 overdue
            $overdue = $daysToDue < 0;
        }

        $block = AccessBlock::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $plan->tenant_id)
            ->where('user_id', $plan->user_id)
            ->whereNull('lifted_at')
            ->get();
        $blockLevel = $this->highestBlock($block);

        return new DunningSnapshot(
            feePlanId: $plan->id,
            userId: $plan->user_id,
            nextInstalmentId: $next?->id,
            nextAmountPaise: (int) ($next?->amount_paise ?? 0),
            nextDueOn: $next?->due_on->toDateString(),
            daysToDue: $daysToDue,
            overdue: $overdue,
            outstandingPaise: $outstanding,
            blockLevel: $blockLevel,
            payUrl: $next?->payment_link_url,
        );
    }

    /** Overdue days for an instalment (>=0 when past due, else 0). */
    public function overdueDays(Instalment $instalment, Carbon $today): int
    {
        $due = $instalment->due_on->copy()->startOfDay();
        $days = (int) $due->diffInDays($today->copy()->startOfDay(), false);

        return max(0, $days);
    }

    /**
     * @param  Collection<int, AccessBlock>  $blocks
     */
    private function highestBlock($blocks): ?string
    {
        if ($blocks->contains(fn (AccessBlock $b) => $b->level->value === 'hard')) {
            return 'hard';
        }
        if ($blocks->contains(fn (AccessBlock $b) => $b->level->value === 'soft')) {
            return 'soft';
        }

        return null;
    }
}
