<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Instalment;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Razorpay payment is captured against an instalment (PRD §6.8;
 * first-class per CLAUDE.md). Side effects (enrolment flip, receipt render) run
 * as queued jobs; reporting/messaging listen here.
 */
final class PaymentCaptured
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Instalment $instalment,
    ) {}
}
