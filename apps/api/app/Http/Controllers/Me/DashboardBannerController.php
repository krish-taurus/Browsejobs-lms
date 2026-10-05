<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\DashboardBanner;
use Illuminate\Http\JsonResponse;

/**
 * Rotating promotional banners for the student dashboard (candidate
 * request, Aug 2026) — entirely CRM-managed, no code deploy needed to add,
 * change, or retire one.
 */
final class DashboardBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = DashboardBanner::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DashboardBanner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image_url' => $b->imageUrl(),
                'link_url' => $b->link_url,
                'link_label' => $b->link_label,
            ]);

        return response()->json(['data' => $banners]);
    }
}
