<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FundingNews;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The Daily Market Brief (public, hour-cached): the freshest curated hiring
 * announcements, reported layoffs and funding signals — every item sourced.
 * The brief's headline is the most recent item's headline (or a composed
 * fallback), which is exactly what the daily email/WhatsApp teases.
 */
final class DailyBriefController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('daily-brief:latest', 3600, function (): array {
            $items = FundingNews::query()
                ->where('active', true)
                ->orderByDesc('published_on')
                ->orderByDesc('id')
                ->limit(12)
                ->get();

            $grouped = ['hiring' => [], 'layoff' => [], 'funding' => []];
            foreach ($items as $n) {
                $grouped[$n->kind][] = [
                    'headline' => $n->headline,
                    'company' => $n->company,
                    'sector' => $n->sector,
                    'round' => $n->round,
                    'hub' => $n->hub,
                    'hiring_lag_months' => $n->hiring_lag_months,
                    'roles' => $n->roles,
                    'skills' => $n->skills,
                    'source_name' => $n->source_name,
                    'source_url' => $n->source_url,
                    'published_on' => $n->published_on->toDateString(),
                ];
            }

            $lead = $items->first();

            return [
                'date' => now()->toDateString(),
                'headline' => $lead?->headline
                    ?? ($lead ? "{$lead->sector} moves in {$lead->hub} — what it means for hiring" : null),
                'items' => $grouped,
                'total' => $items->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
