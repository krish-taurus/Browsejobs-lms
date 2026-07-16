<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Instalment;
use App\Models\Payment;
use App\Support\Razorpay\RazorpayClient;

/**
 * Creates a Razorpay order for an instalment (for the Razorpay Checkout widget)
 * and records a pending Payment. The webhook later reconciles the capture.
 */
final readonly class CreateInstalmentOrder
{
    public function __construct(private RazorpayClient $razorpay) {}

    public function handle(Instalment $instalment): Payment
    {
        $order = $this->razorpay->createOrder(
            $instalment->amount_paise,
            "inst-{$instalment->id}",
            ['fee_plan_id' => (string) $instalment->fee_plan_id, 'seq' => (string) $instalment->seq],
        );

        $instalment->razorpay_order_id = $order->id;
        $instalment->save();

        return Payment::query()->create([
            'tenant_id' => $instalment->tenant_id,
            'fee_plan_id' => $instalment->fee_plan_id,
            'instalment_id' => $instalment->id,
            'razorpay_order_id' => $order->id,
            'amount_paise' => $instalment->amount_paise,
            'status' => PaymentStatus::Created->value,
        ]);
    }
}
