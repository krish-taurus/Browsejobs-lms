<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Splits a GST-inclusive paise total into taxable value + CGST + SGST (PRD §6.8;
 * the ₹30,000 registration fee is inclusive of 18% GST). Integer paise only —
 * the taxable value is rounded and the tax is the remainder, so the parts always
 * sum back to the exact total.
 */
final class GstCalculator
{
    /** @param  int  $rateBps  GST rate in basis points (1800 = 18%). */
    public static function inclusive(int $totalPaise, int $rateBps): GstBreakup
    {
        $taxable = (int) round($totalPaise / (1 + $rateBps / 10_000));
        $gst = $totalPaise - $taxable;
        $cgst = intdiv($gst, 2);
        $sgst = $gst - $cgst;

        return new GstBreakup(
            taxablePaise: $taxable,
            cgstPaise: $cgst,
            sgstPaise: $sgst,
            totalPaise: $totalPaise,
        );
    }
}
