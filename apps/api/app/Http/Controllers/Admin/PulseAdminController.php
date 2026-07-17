<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Engagement\BuildMarketPulse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePulseItemRequest;
use App\Models\PulseDigest;
use App\Models\PulseItem;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Market Pulse curation (PRD §6.19): curate source items, build/preview
 * the daily digest on demand (the schedule also builds it at 06:45).
 */
final class PulseAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'items' => PulseItem::query()->orderByDesc('published_on')->limit(50)->get(),
            'digest' => PulseDigest::query()->orderByDesc('digest_date')->first(),
        ]]);
    }

    public function storeItem(StorePulseItemRequest $request): JsonResponse
    {
        $item = PulseItem::query()->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'title' => $request->string('title')->toString(),
            'url' => $request->string('url')->toString(),
            'source_name' => $request->string('source_name')->toString(),
            'course_slug' => $request->input('course_slug'),
            'note' => $request->input('note'),
            'published_on' => $request->input('published_on', now()->toDateString()),
            'added_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function destroyItem(PulseItem $item): JsonResponse
    {
        $item->update(['is_active' => false]);

        return response()->json(status: 204);
    }

    public function generate(Request $request, BuildMarketPulse $build): JsonResponse
    {
        $digest = $build->handle(
            (int) app(TenantContext::class)->id(),
            $request->user(),
        );

        if ($digest === null) {
            return response()->json(['message' => 'No curated items in the window — add items first.'], 422);
        }

        return response()->json(['data' => $digest]);
    }
}
