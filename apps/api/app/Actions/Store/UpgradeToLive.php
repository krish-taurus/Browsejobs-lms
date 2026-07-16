<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\ProductKind;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Starts a "pay the difference" upgrade from a self-paced enrolment to live (PRD
 * §6.3). Prices the difference (live registration − self-paced price) and begins a
 * purchase; settlement flips the enrolment to live.
 */
final readonly class UpgradeToLive
{
    public function __construct(private StartPurchase $start) {}

    public function handle(User $user, Batch $sourceBatch): ProductPurchase
    {
        return app(TenantContext::class)->run($user->tenant, function () use ($user, $sourceBatch): ProductPurchase {
            $selfPaced = Product::query()->where('sku', "self-paced-{$sourceBatch->id}")->first();
            $live = (int) config('fees.registration_paise');
            $difference = max(0, $live - (int) ($selfPaced?->price_paise ?? 0));

            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $user->tenant_id, 'sku' => "upgrade-{$sourceBatch->id}"],
                [
                    'name' => "Upgrade to live: {$sourceBatch->number}",
                    'feature' => 'live',
                    'kind' => ProductKind::Upgrade->value,
                    'price_paise' => $difference,
                    'grant_amount' => 0,
                    'source_batch_id' => $sourceBatch->id,
                    'active' => true,
                ],
            );

            return $this->start->handle($user, $product, ['batch_id' => $sourceBatch->id]);
        });
    }
}
