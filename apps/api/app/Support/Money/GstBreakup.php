<?php

declare(strict_types=1);

namespace App\Support\Money;

/** An inclusive-GST split of a paise total: taxable + cgst + sgst == total. */
final readonly class GstBreakup
{
    public function __construct(
        public int $taxablePaise,
        public int $cgstPaise,
        public int $sgstPaise,
        public int $totalPaise,
    ) {}
}
