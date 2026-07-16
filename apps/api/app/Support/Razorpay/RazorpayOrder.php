<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

/** A created Razorpay order. */
final readonly class RazorpayOrder
{
    public function __construct(
        public string $id,
        public int $amountPaise,
    ) {}
}
