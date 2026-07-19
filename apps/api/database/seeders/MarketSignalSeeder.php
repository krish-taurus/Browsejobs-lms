<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\RefreshMarketIntel;
use App\Support\Market\SeedMarketIntelSource;
use Illuminate\Database\Seeder;

/** Seeds today's market-intelligence snapshot so the landing boards are demo-able. */
final class MarketSignalSeeder extends Seeder
{
    public function run(): void
    {
        (new RefreshMarketIntel)->handle(new SeedMarketIntelSource);
    }
}
