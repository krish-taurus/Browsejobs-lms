<?php

declare(strict_types=1);

namespace App\Support\Razorpay;

/**
 * A Razorpay subscription handle (PRD §6.17 Career+).
 */
final readonly class RazorpaySubscription
{
    public function __construct(
        public string $id,
        public string $status = 'active',
    ) {}
}
