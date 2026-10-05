<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\FeePlanType;
use Illuminate\Support\Carbon;

/**
 * Computes the instalment schedule for a fee plan (PRD §6.8) — the "schedule
 * previewed before confirm" requirement. Pure + deterministic: instalment 1 is
 * due at checkout, the rest fall on the same calendar day monthly (month-end
 * clamped). A discount is money off what's owed overall, not something that
 * specifically empties the first payment — it is taken off the total before
 * splitting, so every instalment shrinks by its fair share. All amounts in
 * paise; the instalments always sum back to `net = total − discount`.
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

        // Split what's actually owed (after discount) evenly across every
        // instalment, remainder onto instalment 1 — e.g. ₹15,000 net over
        // 2 EMIs is ₹7,500 each, not ₹0 then ₹15,000.
        $base = intdiv($net, $count);
        $rows = [];
        for ($seq = 1; $seq <= $count; $seq++) {
            $amount = $base;
            if ($seq === 1) {
                $amount += $net - $base * $count; // remainder
            }
            $rows[] = [
                'seq' => $seq,
                'amount_paise' => $amount,
                'due_on' => $start->copy()->addMonthsNoOverflow($seq - 1)->toDateString(),
            ];
        }

        return $rows;
    }
}
