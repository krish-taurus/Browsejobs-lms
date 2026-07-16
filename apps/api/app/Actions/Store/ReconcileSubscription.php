<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\ProductKind;
use App\Enums\PurchaseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ProductPurchase;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;

/**
 * Reconciles Razorpay subscription webhooks for Career+ (PRD §6.17). A
 * `subscription.charged` cycle writes a settled purchase (extends the entitlement
 * + issues a receipt); `subscription.cancelled`/`halted` revokes access.
 */
final readonly class ReconcileSubscription
{
    public function __construct(
        private SettlePurchase $settle,
        private EntitlementService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');
        if (! in_array($event, ['subscription.charged', 'subscription.cancelled', 'subscription.halted'], true)) {
            return;
        }

        $entity = (array) data_get($payload, 'payload.subscription.entity', []);
        $subscriptionId = is_string($entity['id'] ?? null) ? $entity['id'] : null;
        if ($subscriptionId === null) {
            return;
        }

        $subscription = Subscription::query()->withoutGlobalScopes()->where('razorpay_subscription_id', $subscriptionId)->first();
        if ($subscription === null) {
            return;
        }

        $tenant = Tenant::query()->find($subscription->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($event, $subscription): void {
            $event === 'subscription.charged'
                ? $this->charge($subscription)
                : $this->stop($subscription, $event);
        });
    }

    private function charge(Subscription $subscription): void
    {
        $product = $subscription->product;
        $amount = (int) ($product?->price_paise ?? config('monetization.career_plus.price_paise', 49_900));

        $purchase = ProductPurchase::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'user_id' => $subscription->user_id,
            'product_id' => $product?->id,
            'subscription_id' => $subscription->id,
            'sku' => $product?->sku ?? 'career-plus',
            'feature' => 'career_plus',
            'kind' => ProductKind::Subscription->value,
            'amount_paise' => $amount,
            'total_paise' => $amount,
            'status' => PurchaseStatus::Pending->value,
        ]);

        $this->settle->handle($purchase);

        $subscription->update([
            'status' => SubscriptionStatus::Active->value,
            'current_period_end' => now()->addDays((int) ($product?->period_days ?? 30)),
        ]);
    }

    private function stop(Subscription $subscription, string $event): void
    {
        $subscription->update([
            'status' => ($event === 'subscription.halted' ? SubscriptionStatus::Halted : SubscriptionStatus::Cancelled)->value,
            'cancelled_at' => now(),
        ]);

        $user = User::query()->find($subscription->user_id);
        if ($user !== null) {
            $this->entitlements->revokeEntitlement($user, 'career_plus');
        }
    }
}
