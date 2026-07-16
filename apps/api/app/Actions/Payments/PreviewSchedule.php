<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanType;
use Illuminate\Support\Carbon;

/**
 * Computes the instalment schedule for a fee plan (PRD §6.8) — the "schedule
 * previewed before confirm" requirement. Pure + deterministic: instalment 1 is
 * due at checkout, the rest fall on the same calendar day monthly (month-end
 * clamped). Any discount is applied to instalment 1. All amounts in paise; the
 * instalments always sum back to `net = total − discount`.
 *
 * @phpstan-type ScheduleRow array{seq: int, amount_paise: int, due_on: string}
 */
final class PreviewSchedule
{
    /**
     * @return list<ScheduleRow>
     */
    public function handle(FeePlanType $type, int $emiCount, int $totalPaise, int $discountPaise = 0, ?Carbon $start = null): array
    {
        $start ??= Carbon::today();
        $count = $type === FeePlanType::Single ? 1 : max(1, $emiCount);
        $net = max(0, $totalPaise - $discountPaise);

        // Split the FULL total evenly, remainder onto instalment 1, then apply
        // the discount to instalment 1 so the parts sum to `net`.
        $base = intdiv($totalPaise, $count);
        $rows = [];
        for ($seq = 1; $seq <= $count; $seq++) {
            $amount = $base;
            if ($seq === 1) {
                $amount += $totalPaise - $base * $count; // remainder
                $amount -= $discountPaise;               // discount on instalment 1
            }
            $rows[] = [
                'seq' => $seq,
                'amount_paise' => max(0, $amount),
                'due_on' => $start->copy()->addMonthsNoOverflow($seq - 1)->toDateString(),
            ];
        }

        // Guard: if a large discount zeroed instalment 1 below the remainder,
        // ensure the schedule still sums to net by adjusting instalment 1.
        $sum = array_sum(array_column($rows, 'amount_paise'));
        if ($sum !== $net && $rows !== []) {
            $rows[0]['amount_paise'] = max(0, $rows[0]['amount_paise'] + ($net - $sum));
        }

        return $rows;
    }
}
