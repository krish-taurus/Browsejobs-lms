<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Models\ProductPurchase;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * Reconciles a Razorpay webhook against a product purchase (PRD §6.17). Matches by
 * order id; no-ops when the event is not a store capture (the fee reconciler owns
 * instalment orders). Idempotent — SettlePurchase guards a re-delivered capture.
 */
final readonly class ReconcilePurchase
{
    public function __construct(private SettlePurchase $settle) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?ProductPurchase
    {
        $event = (string) ($payload['event'] ?? '');
        if (! in_array($event, ['payment.captured', 'order.paid'], true)) {
            return null;
        }

        $paymentEntity = (array) data_get($payload, 'payload.payment.entity', []);
        $orderId = is_string($paymentEntity['order_id'] ?? null) ? $paymentEntity['order_id'] : null;
        $paymentId = is_string($paymentEntity['id'] ?? null) ? $paymentEntity['id'] : null;
        if ($orderId === null) {
            return null;
        }

        $purchase = ProductPurchase::query()->withoutGlobalScopes()->where('razorpay_order_id', $orderId)->first();
        if ($purchase === null) {
            return null;
        }

        $tenant = Tenant::query()->find($purchase->tenant_id);
        if ($tenant === null) {
            return null;
        }

        return app(TenantContext::class)->run($tenant, fn () => $this->settle->handle($purchase, $paymentId));
    }
}
