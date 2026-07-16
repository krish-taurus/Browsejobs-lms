<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\SubscriptionStatus;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Tenancy\TenantContext;

/**
 * Starts a Career+ subscription (PRD §6.17). Creates the Razorpay recurring
 * subscription; the first `subscription.charged` webhook grants the career_plus
 * entitlement + a GST receipt.
 */
final readonly class StartSubscription
{
    public function __construct(private RazorpayClient $razorpay) {}

    public function handle(User $user, Product $product): Subscription
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $product): Subscription {
            $rzp = $this->razorpay->createSubscription(
                $product->price_paise,
                (int) ($product->period_days ?? 30),
                ['user_id' => (string) $user->id, 'product' => $product->sku],
            );

            return Subscription::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'razorpay_subscription_id' => $rzp->id,
                'status' => SubscriptionStatus::Active->value,
            ]);
        });
    }
}
