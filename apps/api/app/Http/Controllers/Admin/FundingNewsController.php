<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshMarketIntel;
use App\Models\FundingNews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin curation of Funding Radar items ("our research"): every item
 * requires its public source, and saving re-queues the snapshot refresh so
 * the homepage updates within the hour cache. Platform-global (like
 * settings) — behind can:manage-settings.
 */
final class FundingNewsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => FundingNews::query()->orderByDesc('published_on')->limit(50)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:funding,hiring,layoff'],
            'headline' => ['nullable', 'string', 'max:200'],
            'company' => ['nullable', 'string', 'max:120'],
            'sector' => ['required', 'string', 'max:80'],
            'round' => ['required', 'string', 'max:80'],
            'hub' => ['required', 'string', 'max:80'],
            'hiring_lag_months' => ['required', 'integer', 'min:1', 'max:12'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'max:80'],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*' => ['string', 'max:80'],
            'source_name' => ['required', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'published_on' => ['required', 'date'],
        ]);

        // A named company must carry a verifiable public source link (Spec §3).
        if (($validated['company'] ?? null) !== null && ($validated['source_url'] ?? null) === null) {
            return response()->json(['error' => [
                'code' => 'source_required',
                'message' => 'A named company requires a public source URL.',
                'details' => null,
            ]], 422);
        }

        $news = FundingNews::query()->create($validated);
        RefreshMarketIntel::dispatch();

        return response()->json(['data' => $news], 201);
    }

    public function destroy(FundingNews $fundingNews): JsonResponse
    {
        $fundingNews->update(['active' => false]);
        RefreshMarketIntel::dispatch();

        return response()->json(['data' => $fundingNews->refresh()]);
    }
}
