<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\ReconcilePayment;
use App\Enums\FeePlanType;
use App\Models\Tenant;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Storage;

/**
 * A payment link payment fires `payment.captured` carrying only an order id.
 * If the webhook has no `payment_link.paid` subscription, that used to leave
 * real money unreconciled until someone pressed Sync — so the reconciler now
 * traces the order back to its instalment on its own.
 */
beforeEach(function () {
    Storage::fake('s3');
    $this->razorpay = new FakeRazorpayClient;
    app()->instance(RazorpayClient::class, $this->razorpay);
    $this->tenant = Tenant::factory()->create();

    ['batch' => $batch, 'student' => $student] = reservedMemberIn($this->tenant);

    $this->plan = withinTenant($this->tenant, fn () => app(CreateFeePlan::class)->handle(
        $this->tenant, $student, $batch, FeePlanType::Emi, 3,
    ));

    $this->instalment = $this->plan->instalments->firstWhere('seq', 1);
});

it('settles a link payment from payment.captured alone, with no link id in the payload', function () {
    $this->razorpay->orders['order_ABC'] = ['receipt' => "inst-{$this->instalment->id}", 'amount_paise' => $this->instalment->amount_paise];

    $payment = app(ReconcilePayment::class)->handle([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_ABC', 'order_id' => 'order_ABC', 'method' => 'upi']]],
    ]);

    expect($payment)->not->toBeNull()
        ->and($this->instalment->fresh()->status->value)->toBe('paid')
        // The order id is remembered, so the next event needs no API call.
        ->and($this->instalment->fresh()->razorpay_order_id)->toBe('order_ABC');
});

it('handles a regenerated link whose receipt carries a suffix', function () {
    $this->razorpay->orders['order_XYZ'] = ['receipt' => "inst-{$this->instalment->id}-f5zjrt"];

    app(ReconcilePayment::class)->handle([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_XYZ', 'order_id' => 'order_XYZ', 'method' => 'upi']]],
    ]);

    expect($this->instalment->fresh()->status->value)->toBe('paid');
});

it('ignores an order that is not one of ours', function () {
    $this->razorpay->orders['order_OTHER'] = ['receipt' => 'store-purchase-99'];

    $payment = app(ReconcilePayment::class)->handle([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_OTHER', 'order_id' => 'order_OTHER']]],
    ]);

    expect($payment)->toBeNull()
        ->and($this->instalment->fresh()->status->value)->toBe('pending');
});
