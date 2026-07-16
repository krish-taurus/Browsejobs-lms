<?php

declare(strict_types=1);

use App\Actions\Store\CancelSubscription;
use App\Actions\Store\ReconcilePurchase;
use App\Actions\Store\ReconcileSubscription;
use App\Actions\Store\RefundPurchase;
use App\Actions\Store\StartPurchase;
use App\Actions\Store\StartSubscription;
use App\Enums\ProductKind;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Razorpay\FakeRazorpayClient;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $this->fake = new FakeRazorpayClient;
    app()->instance(RazorpayClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->svc = app(EntitlementService::class);
});

function settledPack(Tenant $tenant, User $student): ProductPurchase
{
    $pack = Product::factory()->for($tenant)->create(['sku' => 'voice-3pack', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack->value, 'price_paise' => 59_900, 'grant_amount' => 3]);
    $purchase = withinTenant($tenant, fn () => app(StartPurchase::class)->handle($student, $pack));
    app(ReconcilePurchase::class)->handle(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
        'id' => 'pay_R', 'order_id' => $purchase->razorpay_order_id, 'amount' => 59_900,
    ]]]]);

    return $purchase->fresh();
}

it('refund claws back unspent credits and audits', function () {
    $purchase = settledPack($this->tenant, $this->student);
    withinTenant($this->tenant, fn () => $this->svc->consumeCredits($this->student, 'voice_mock', 1, 'used')); // 2 left

    withinTenant($this->tenant, fn () => app(RefundPurchase::class)->handle($purchase->fresh(), 'duplicate charge'));

    expect($this->svc->balance($this->student, 'voice_mock'))->toBe(0)
        ->and(ProductPurchase::withoutGlobalScopes()->find($purchase->id)->status->value)->toBe('refunded')
        ->and($this->fake->refunds)->toHaveCount(1)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'store.purchase_refunded')->count())->toBe(1);
});

it('subscribes and grants career_plus on the first charge, revokes on cancel', function () {
    $product = Product::factory()->for($this->tenant)->careerPlus()->create();

    $subscription = withinTenant($this->tenant, fn () => app(StartSubscription::class)->handle($this->student, $product));
    expect($subscription->razorpay_subscription_id)->toStartWith('sub_TEST');

    // The first cycle charge grants the entitlement + a receipt.
    app(ReconcileSubscription::class)->handle(['event' => 'subscription.charged', 'payload' => ['subscription' => ['entity' => [
        'id' => $subscription->razorpay_subscription_id,
    ]]]]);

    expect($this->svc->hasEntitlement($this->student, 'career_plus'))->toBeTrue()
        ->and(ProductPurchase::withoutGlobalScopes()->where('feature', 'career_plus')->where('status', 'captured')->count())->toBe(1);

    // Cancellation webhook revokes access.
    app(ReconcileSubscription::class)->handle(['event' => 'subscription.cancelled', 'payload' => ['subscription' => ['entity' => [
        'id' => $subscription->razorpay_subscription_id,
    ]]]]);

    expect($this->svc->hasEntitlement($this->student, 'career_plus'))->toBeFalse()
        ->and(Subscription::withoutGlobalScopes()->find($subscription->id)->status->value)->toBe('cancelled');
});

it('cancels a subscription through the action', function () {
    $product = Product::factory()->for($this->tenant)->careerPlus()->create();
    $subscription = withinTenant($this->tenant, fn () => app(StartSubscription::class)->handle($this->student, $product));

    withinTenant($this->tenant, fn () => app(CancelSubscription::class)->handle($subscription));

    expect(Subscription::withoutGlobalScopes()->find($subscription->id)->status->value)->toBe('cancelled')
        ->and($this->fake->cancelled)->toContain($subscription->razorpay_subscription_id);
});
