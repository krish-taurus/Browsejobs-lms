<?php

declare(strict_types=1);

use App\Jobs\RefreshMarketIntel;
use App\Models\MarketSignal;
use App\Support\Market\SeedMarketIntelSource;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

it('returns empty kinds when no snapshot exists (frontend falls back)', function () {
    getJson('/api/v1/market-intel')->assertOk()
        ->assertJsonPath('data.city_pulse', null)
        ->assertJsonPath('data.funding', null)
        ->assertJsonPath('data.outlook', null);
});

it('refresh job stores one dated row per kind, idempotently', function () {
    (new RefreshMarketIntel)->handle(new SeedMarketIntelSource);
    (new RefreshMarketIntel)->handle(new SeedMarketIntelSource); // same day — overwrite, not duplicate

    expect(MarketSignal::query()->count())->toBe(3)
        ->and(MarketSignal::latest_('funding')->payload)->toHaveCount(4)
        ->and(MarketSignal::latest_('outlook')->payload[0]['track'])->toBe('Data Engineering');
});

it('serves the latest snapshot with its effective date', function () {
    Cache::flush();
    (new RefreshMarketIntel)->handle(new SeedMarketIntelSource);

    getJson('/api/v1/market-intel')->assertOk()
        ->assertJsonPath('data.effective_on', now()->toDateString())
        ->assertJsonPath('data.city_pulse.0.name', 'Bengaluru')
        ->assertJsonPath('data.funding.0.sector', 'Fintech');
});

it('is a platform-global surface — no tenant data can leak through it', function () {
    // The table has no tenant_id and the payload is sector-level public news;
    // assert the response never contains tenant-scoped structures.
    (new RefreshMarketIntel)->handle(new SeedMarketIntelSource);
    $json = getJson('/api/v1/market-intel')->json();

    expect(json_encode($json))->not->toContain('tenant_id')
        ->and(json_encode($json))->not->toContain('email');
});
