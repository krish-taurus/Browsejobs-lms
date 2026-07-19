<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MarketSignal;
use App\Support\Market\MarketIntelSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Daily market-intelligence refresh: pulls a snapshot from the configured
 * source and upserts one dated row per kind. Idempotent — re-running a day
 * overwrites that day's snapshot; history is kept for the timeline feature.
 */
final class RefreshMarketIntel implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(MarketIntelSource $source): void
    {
        $snapshot = $source->snapshot();
        $today = now()->startOfDay();

        foreach ($snapshot as $kind => $payload) {
            MarketSignal::query()->updateOrCreate(
                ['kind' => $kind, 'effective_on' => $today],
                ['payload' => $payload, 'source' => $source::class],
            );
        }

        Cache::forget('market-intel:latest');
    }
}
