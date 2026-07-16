<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductPurchaseResource;
use App\Models\ProductPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revenue-by-product dashboard (PRD §6.17). Aggregates captured/refunded product
 * purchases per feature. Gated by `can:manage-monetization`.
 */
final class RevenueController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ProductPurchase::query()
            ->whereIn('status', [PurchaseStatus::Captured->value, PurchaseStatus::Refunded->value])
            ->get(['feature', 'status', 'amount_paise']);

        $gross = (int) $rows->where('status', PurchaseStatus::Captured->value)->sum('amount_paise');
        $refunds = (int) $rows->where('status', PurchaseStatus::Refunded->value)->sum('amount_paise');

        $byFeature = $rows->groupBy(fn ($r) => $r->feature ?? 'other')->map(function ($group, $feature) {
            $captured = $group->where('status', PurchaseStatus::Captured->value);
            $refunded = $group->where('status', PurchaseStatus::Refunded->value);

            return [
                'feature' => $feature,
                'count' => $captured->count(),
                'gross_paise' => (int) $captured->sum('amount_paise'),
                'refunds_paise' => (int) $refunded->sum('amount_paise'),
            ];
        })->values()->all();

        return response()->json(['data' => [
            'gross_paise' => $gross,
            'refunds_paise' => $refunds,
            'net_paise' => $gross - $refunds,
            'by_feature' => $byFeature,
        ]]);
    }

    public function purchases(Request $request): JsonResponse
    {
        $purchases = ProductPurchase::query()
            ->with('student:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(25);

        return ProductPurchaseResource::collection($purchases)->response();
    }
}
