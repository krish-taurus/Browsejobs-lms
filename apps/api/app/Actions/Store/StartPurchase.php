<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\User;
use App\Support\Razorpay\RazorpayClient;
use App\Support\Tenancy\TenantContext;

/**
 * Starts an in-app purchase (PRD §6.17): records a pending purchase and creates a
 * Razorpay order (server-owned amount). The webhook settles it on capture.
 */
final readonly class StartPurchase
{
    public function __construct(private RazorpayClient $razorpay) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function handle(User $user, Product $product, array $meta = []): ProductPurchase
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $product, $meta): ProductPurchase {
            $purchase = ProductPurchase::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'feature' => $product->feature,
                'kind' => $product->kind->value,
                'amount_paise' => $product->price_paise,
                'total_paise' => $product->price_paise,
                'status' => PurchaseStatus::Pending->value,
                'meta' => $meta ?: null,
            ]);

            $order = $this->razorpay->createOrder(
                $product->price_paise,
                "purchase-{$purchase->id}",
                ['purchase_id' => (string) $purchase->id],
            );

            $purchase->update(['razorpay_order_id' => $order->id]);

            return $purchase->fresh();
        });
    }
}
