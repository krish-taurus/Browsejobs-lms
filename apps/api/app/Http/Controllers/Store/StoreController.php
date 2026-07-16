<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Actions\Store\CancelSubscription;
use App\Actions\Store\StartPurchase;
use App\Actions\Store\StartSubscription;
use App\Actions\Store\UpgradeToLive;
use App\Enums\EntitlementStatus;
use App\Enums\ProductKind;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PurchaseRequest;
use App\Http\Resources\ProductPurchaseResource;
use App\Http\Resources\ProductResource;
use App\Models\Batch;
use App\Models\CreditWallet;
use App\Models\Entitlement;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Subscription;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student store + wallet (PRD §6.17). Runs under auth:sanctum without tenant.user,
 * so work is wrapped in the student's tenant context and scoped to their user id.
 */
final class StoreController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user): JsonResponse {
            $products = Product::query()->where('active', true)->orderBy('id')->get();
            $wallets = CreditWallet::query()->where('user_id', $user->id)->get();
            $entitlements = Entitlement::query()->where('user_id', $user->id)
                ->where('status', EntitlementStatus::Active->value)->get();

            return response()->json(['data' => [
                'products' => ProductResource::collection($products),
                'wallets' => $wallets->map(fn ($w) => ['feature' => $w->feature, 'balance' => $w->balance])->all(),
                'entitlements' => $entitlements->map(fn ($e) => [
                    'key' => $e->key, 'kind' => $e->kind, 'expires_at' => $e->expires_at?->toIso8601String(),
                ])->all(),
            ]]);
        });
    }

    public function purchase(PurchaseRequest $request, StartPurchase $start): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $request, $start): JsonResponse {
            $product = Product::query()->where('active', true)->findOrFail($request->integer('product_id'));

            abort_if($product->kind === ProductKind::Subscription, 422, 'Use the subscribe endpoint for subscriptions.');

            $purchase = $start->handle($user, $product);

            return response()->json(['data' => [
                'purchase_id' => $purchase->id,
                'razorpay_order_id' => $purchase->razorpay_order_id,
                'amount_paise' => $purchase->amount_paise,
            ]], 201);
        });
    }

    public function purchases(Request $request): JsonResponse
    {
        $purchases = ProductPurchase::query()->withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return ProductPurchaseResource::collection($purchases)->response();
    }

    public function entitlements(Request $request): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user): JsonResponse {
            $wallets = CreditWallet::query()->where('user_id', $user->id)->get();
            $entitlements = Entitlement::query()->where('user_id', $user->id)->get();

            return response()->json(['data' => [
                'wallets' => $wallets->map(fn ($w) => ['feature' => $w->feature, 'balance' => $w->balance])->all(),
                'entitlements' => $entitlements->map(fn ($e) => [
                    'key' => $e->key, 'kind' => $e->kind, 'status' => $e->status->value,
                    'expires_at' => $e->expires_at?->toIso8601String(),
                ])->all(),
            ]]);
        });
    }

    public function subscribe(Request $request, StartSubscription $start): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $start): JsonResponse {
            $product = Product::query()->where('kind', ProductKind::Subscription->value)->where('active', true)->firstOrFail();
            $subscription = $start->handle($user, $product);

            return response()->json(['data' => [
                'subscription_id' => $subscription->id,
                'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            ]], 201);
        });
    }

    public function cancel(Request $request, CancelSubscription $cancel): JsonResponse
    {
        $user = $request->user();

        return app(TenantContext::class)->run($user->tenant, function () use ($user, $cancel): JsonResponse {
            $subscription = Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', SubscriptionStatus::Active->value)
                ->orderByDesc('id')
                ->firstOrFail();

            $cancel->handle($subscription);

            return response()->json(['ok' => true]);
        });
    }

    public function upgrade(Request $request, int $batch, UpgradeToLive $upgrade): JsonResponse
    {
        $user = $request->user();
        $model = Batch::query()->withoutGlobalScopes()->findOrFail($batch);

        abort_unless($this->entitlements->hasEntitlement($user, "self_paced:{$model->id}"), 403);

        $purchase = $upgrade->handle($user, $model);

        return response()->json(['data' => [
            'purchase_id' => $purchase->id,
            'amount_paise' => $purchase->amount_paise,
        ]], 201);
    }
}
