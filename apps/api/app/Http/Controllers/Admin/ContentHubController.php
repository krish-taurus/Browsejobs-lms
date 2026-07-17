<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Engagement\PublishContentItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentItemRequest;
use App\Models\ContentHubItem;
use Illuminate\Http\JsonResponse;

/**
 * Admin Content Hub (PRD §6.19): manual releases today; YouTube RSS/Instagram
 * Graph ingestion plugs in behind the same table when credentials arrive.
 */
final class ContentHubController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ContentHubItem::query()->orderByDesc('published_at')->limit(50)->get(),
        ]);
    }

    public function store(StoreContentItemRequest $request, PublishContentItem $publish): JsonResponse
    {
        $item = $publish->handle($request->validated(), $request->user());

        return response()->json(['data' => $item], 201);
    }

    public function destroy(ContentHubItem $item): JsonResponse
    {
        $item->update(['is_active' => false]);

        return response()->json(status: 204);
    }
}
