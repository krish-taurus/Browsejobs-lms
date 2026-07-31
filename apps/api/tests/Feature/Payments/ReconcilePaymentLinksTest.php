<?php

declare(strict_types=1);

use App\Actions\Payments\CreateFeePlan;
use App\Actions\Payments\SendPaymentLink;
use App\Enums\FeePlanType;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $this->razorpay = new FakeRazorpayClient;
    app()->instance(RazorpayClient::class, $this->razorpay);
    $this->tenant = Tenant::factory()->create();
});

function planWithLink(Tenant $tenant, FakeRazorpayClient $razorpay): array
{
    ['batch' => $batch, 'student' => $student] = reservedMemberIn($tenant);

    $plan = withinTenant($tenant, fn () => app(CreateFeePlan::class)->handle(
        $tenant, $student, $batch, FeePlanType::Emi, 3,
    ));

    $first = $plan->instalments->firstWhere('seq', 1);
    $first->update(['razorpay_payment_link_id' => 'plink_TESTLINK1']);

    return [$plan, $first];
}

it('settles a pending instalment whose Razorpay link is paid', function () {
    [$plan, $first] = planWithLink($this->tenant, $this->razorpay);
    $this->razorpay->linkStatuses['plink_TESTLINK1'] = [
        'status' => 'paid', 'order_id' => 'order_X1', 'payment_id' => 'pay_X1', 'method' => 'upi',
    ];

    $this->artisan('fees:reconcile-links')->assertExitCode(0);

    expect($first->fresh()->status->value)->toBe('paid')
        ->and($plan->fresh()->instalments->firstWhere('seq', 2)->status->value)->toBe('pending');
});

it('resending a payment link reuses the existing Razorpay link', function () {
    [, $first] = planWithLink($this->tenant, $this->razorpay);
    $first->update(['payment_link_url' => 'https://rzp.test/l/plink_TESTLINK1']);

    withinTenant($this->tenant, fn () => app(SendPaymentLink::class)->handle($first->fresh()));

    expect($this->razorpay->links)->toHaveCount(0) // no second link created
        ->and($first->fresh()->razorpay_payment_link_id)->toBe('plink_TESTLINK1');
});

it('leaves unpaid links untouched and is idempotent on re-runs', function () {
    [, $first] = planWithLink($this->tenant, $this->razorpay);
    // Default fake status is 'created' (unpaid).

    $this->artisan('fees:reconcile-links')->assertExitCode(0);
    expect($first->fresh()->status->value)->toBe('pending');

    $this->razorpay->linkStatuses['plink_TESTLINK1'] = [
        'status' => 'paid', 'order_id' => 'order_X1', 'payment_id' => 'pay_X1', 'method' => 'upi',
    ];
    $this->artisan('fees:reconcile-links')->assertExitCode(0);
    $this->artisan('fees:reconcile-links')->assertExitCode(0); // second run: no double-settle

    expect($first->fresh()->status->value)->toBe('paid')
        ->and(Payment::withoutGlobalScopes()->where('razorpay_payment_id', 'pay_X1')->count())->toBe(1);
});
