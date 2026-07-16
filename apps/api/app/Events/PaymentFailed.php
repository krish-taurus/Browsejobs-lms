<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Instalment;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Razorpay payment fails against an instalment (PRD §6.8). Dunning
 * automations (P2.3) listen here.
 */
final class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Instalment $instalment,
    ) {}
}
