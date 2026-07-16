<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Store\PublishSelfPacedCourse;
use App\Actions\Store\RefundPurchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\RefundRequest;
use App\Http\Requests\Store\StoreProductRequest;
use App\Http\Requests\Store\UpdateMonetizationSettingsRequest;
use App\Http\Requests\Store\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Http\JsonResponse;

/**
 * Admin monetization manager (PRD §6.17): settings singleton + product catalog +
 * self-paced publish + refunds. Gated by `can:manage-monetization`; route-model
 * binding is tenant-scoped so foreign ids 404.
 */
final class MonetizationController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function settings(): JsonResponse
    {
        $settings = $this->entitlements->settings();

        return response()->json(['data' => $settings->only([
            'cv_free_grants', 'voice_included_live', 'voice_included_self_paced',
            'self_paced_pct_bps', 'text_practice_enabled',
        ])]);
    }

    public function updateSettings(UpdateMonetizationSettingsRequest $request): JsonResponse
    {
        $settings = $this->entitlements->settings();
        $settings->fill($request->validated())->save();

        return response()->json(['data' => $settings->only([
            'cv_free_grants', 'voice_included_live', 'voice_included_self_paced',
            'self_paced_pct_bps', 'text_practice_enabled',
        ])]);
    }

    public function products(): JsonResponse
    {
        $products = Product::query()->orderByDesc('id')->get();

        return ProductResource::collection($products)->response();
    }

    public function storeProduct(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function updateProduct(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return (new ProductResource($product->fresh()))->response();
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        $product->update(['active' => false]);

        return response()->json(['ok' => true]);
    }

    public function publishSelfPaced(Batch $batch, PublishSelfPacedCourse $publish): JsonResponse
    {
        $product = $publish->handle($batch, request()->user());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function refund(RefundRequest $request, ProductPurchase $purchase, RefundPurchase $refund): JsonResponse
    {
        $refund->handle($purchase, $request->string('reason')->toString(), $request->user());

        return response()->json(['ok' => true]);
    }
}
