<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MarketSignal;
use App\Support\Market\FeedMarketIntel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public market-intelligence snapshot for the landing page (Market Pulse +
 * Intelligence Board). Latest dated row per kind, cached for an hour; an
 * empty object per kind when no snapshot exists so the frontend falls back
 * to its bundled illustrative content.
 *
 * `feed` is the counted part: track volumes and skill movement measured over
 * job_feed_items. It carries its own `sufficient` flag, and the boards must
 * read that before printing a figure — below the sample floor there are no
 * numbers to print, which is the honest answer rather than a rounded one.
 */
final class MarketIntelController extends Controller
{
    public function __invoke(FeedMarketIntel $intel): JsonResponse
    {
        $data = Cache::remember('market-intel:latest', 3600, function () use ($intel): array {
            $out = [];
            foreach ([MarketSignal::KIND_CITY_PULSE, MarketSignal::KIND_FUNDING, MarketSignal::KIND_OUTLOOK, MarketSignal::KIND_DOMAIN_RANK, MarketSignal::KIND_FUNDING_RADAR] as $kind) {
                $row = MarketSignal::latest_($kind);
                $out[$kind] = $row?->payload;
                $out['effective_on'] ??= $row?->effective_on?->toDateString();
            }

            $out['feed'] = $intel->snapshot();

            return $out;
        });

        return response()->json(['data' => $data]);
    }
}
