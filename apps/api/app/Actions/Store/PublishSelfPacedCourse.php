<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Enums\ProductKind;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a completed batch's recordings as a sellable self-paced course (PRD
 * §6.3), priced at the tenant's configurable % of the live registration fee.
 * One-click; upserts the catalog product.
 */
final readonly class PublishSelfPacedCourse
{
    public function __construct(
        private EntitlementService $entitlements,
        private AuditLogger $audit,
    ) {}

    public function handle(Batch $batch, ?User $actor = null): Product
    {
        return app(TenantContext::class)->run($batch->tenant, function () use ($batch, $actor): Product {
            if ($batch->status !== 'completed') {
                throw ValidationException::withMessages(['batch' => 'Only a completed batch can be published as self-paced.']);
            }

            $pct = $this->entitlements->settings()->self_paced_pct_bps;
            $price = intdiv((int) config('fees.registration_paise') * $pct, 10_000);

            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $batch->tenant_id, 'sku' => "self-paced-{$batch->id}"],
                [
                    'name' => "Self-paced: {$batch->number}",
                    'feature' => 'self_paced',
                    'kind' => ProductKind::SelfPaced->value,
                    'price_paise' => $price,
                    'grant_amount' => 0,
                    'source_batch_id' => $batch->id,
                    'active' => true,
                ],
            );

            $this->audit->log('store.self_paced_published', $product, ['batch_id' => $batch->id, 'price_paise' => $price], $actor);

            return $product;
        });
    }
}
