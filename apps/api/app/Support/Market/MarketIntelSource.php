<?php

declare(strict_types=1);

namespace App\Support\Market;

/**
 * Provider-switchable source of market-intelligence snapshots (mirrors the
 * AI-gateway pattern, ADR 0016). The default SeedMarketIntelSource returns the
 * curated sector-level snapshot; a news-aggregation transport can replace it
 * via the container without touching consumers.
 */
interface MarketIntelSource
{
    /** @return array{city_pulse: array<int, mixed>, funding: array<int, mixed>, outlook: array<int, mixed>} */
    public function snapshot(): array;
}
