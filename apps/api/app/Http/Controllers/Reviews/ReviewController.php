<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reviews wall (paginated; filterable by source). Aggregate figures are
 * computed from the stored reviews so the page never overstates what's loaded.
 */
final class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()
            ->where('is_active', true)
            ->when(
                in_array($request->query('source'), Review::SOURCES, true),
                fn ($q) => $q->where('source', $request->query('source')),
            )
            ->orderBy('sort_order')
            ->orderByDesc('reviewed_on')
            ->orderByDesc('id');

        $page = $query->paginate(
            perPage: min(50, max(6, (int) $request->query('per_page', 12))),
        );

        $aggregate = Review::query()
            ->where('is_active', true)
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->first();

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'average_rating' => round((float) ($aggregate->average ?? 0), 1),
            ],
        ]);
    }
}
