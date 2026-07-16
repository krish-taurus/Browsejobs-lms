<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Tenancy\TenantContext;

/**
 * Cancels a Career+ subscription (PRD §6.17). Stops future charges; the active
 * entitlement runs out at its current period end (or the cancellation webhook
 * revokes it).
 */
final readonly class CancelSubscription
{
    public function __construct(private RazorpayClient $razorpay) {}

    public function handle(Subscription $subscription): Subscription
    {
        return app(TenantContext::class)->run($subscription->tenant, function () use ($subscription): Subscription {
            if ($subscription->razorpay_subscription_id !== null) {
                $this->razorpay->cancelSubscription($subscription->razorpay_subscription_id);
            }

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }
}
