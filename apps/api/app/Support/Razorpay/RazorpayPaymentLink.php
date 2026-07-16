<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

/** A created Razorpay personalized payment link. */
final readonly class RazorpayPaymentLink
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
