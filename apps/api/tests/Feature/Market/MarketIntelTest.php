<?php

declare(strict_types=1);

use App\Jobs\RefreshMarketIntel;
use App\Jobs\SendDailyBrief;
use App\Models\FundingNews;
use App\Models\Lead;
use App\Models\MarketSignal;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Market\SeedMarketIntelSource;
use App\Support\Messaging\Messenger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

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

    expect(MarketSignal::query()->count())->toBe(5)
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

it('ranks hot domains and includes the funding radar in the snapshot', function () {
    (new RefreshMarketIntel)->handle(app(SeedMarketIntelSource::class));
    Cache::flush();

    $data = getJson('/api/v1/market-intel')->assertOk()->json('data');

    expect($data['domain_rank'][0]['rank'])->toBe(1)
        ->and($data['domain_rank'][0]['domain'])->toBe('AI & Data')
        ->and(count($data['domain_rank']))->toBe(8)
        ->and($data['funding_radar'][0]['company'])->toBeNull() // samples carry no company
        ->and($data['funding_radar'][0]['source_name'])->toBe('Curated sample');
});

it('lets a super admin publish a sourced funding item that reaches the radar', function () {
    $this->seed(RolePermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    $super = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $super->assignRole('super-admin');
    Sanctum::actingAs($super);

    \Pest\Laravel\postJson('/api/v1/admin/funding-news', [
        'company' => 'Acme Fintech', 'sector' => 'Fintech', 'round' => 'Series C',
        'hub' => 'Bengaluru', 'hiring_lag_months' => 3,
        'roles' => ['Data Engineering'], 'skills' => ['SQL', 'Spark'],
        'source_name' => 'Economic Times', 'source_url' => 'https://example.com/story',
        'published_on' => '2026-07-18',
    ])->assertCreated();

    (new RefreshMarketIntel)->handle(app(SeedMarketIntelSource::class));
    Cache::flush();
    $radar = getJson('/api/v1/market-intel')->json('data.funding_radar');

    expect($radar[0]['company'])->toBe('Acme Fintech')
        ->and($radar[0]['source_url'])->toBe('https://example.com/story');
});

it('refuses a named company without a public source link', function () {
    $this->seed(RolePermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    $super = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $super->assignRole('super-admin');
    Sanctum::actingAs($super);

    \Pest\Laravel\postJson('/api/v1/admin/funding-news', [
        'company' => 'Acme Fintech', 'sector' => 'Fintech', 'round' => 'Series C',
        'hub' => 'Bengaluru', 'hiring_lag_months' => 3,
        'roles' => ['Data Engineering'], 'skills' => ['SQL'],
        'source_name' => 'Economic Times', 'published_on' => '2026-07-18',
    ])->assertStatus(422)->assertJsonPath('error.code', 'source_required');
});

it('locks funding-news curation behind manage-settings', function () {
    $this->seed(RolePermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin'); // plain institute admin — not super
    Sanctum::actingAs($admin);

    \Pest\Laravel\getJson('/api/v1/admin/funding-news')->assertForbidden();
});

it('serves the daily brief grouped by kind with sourced items', function () {
    FundingNews::query()->create([
        'kind' => 'hiring', 'headline' => 'A Bengaluru GCC is adding data teams', 'company' => 'Acme GCC',
        'sector' => 'GCC expansion', 'round' => 'Hiring announced', 'hub' => 'Bengaluru', 'hiring_lag_months' => 2,
        'roles' => ['Data Engineering'], 'skills' => ['SQL'], 'source_name' => 'ET', 'source_url' => 'https://example.com/a',
        'published_on' => '2026-07-19',
    ]);
    FundingNews::query()->create([
        'kind' => 'layoff', 'headline' => null, 'company' => 'Retailer X', 'sector' => 'E-commerce',
        'round' => 'Restructuring reported', 'hub' => 'NCR', 'hiring_lag_months' => 1,
        'roles' => [], 'skills' => [], 'source_name' => 'Mint', 'source_url' => 'https://example.com/b',
        'published_on' => '2026-07-18',
    ]);
    Cache::flush();

    $data = getJson('/api/v1/brief')->assertOk()->json('data');

    expect($data['headline'])->toBe('A Bengaluru GCC is adding data teams')
        ->and($data['items']['hiring'][0]['company'])->toBe('Acme GCC')
        ->and($data['items']['layoff'][0]['source_url'])->toBe('https://example.com/b')
        ->and($data['total'])->toBe(2);
});

it('sends the daily brief teaser to open leads once per day', function () {
    $tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
    MessageTemplate::factory()->for($tenant)->create([
        'key' => 'daily_brief', 'channel' => 'whatsapp', 'body' => '{{headline}} {{name}} {{link}}',
    ]);
    Lead::factory()->for($tenant)->create(['phone' => '+919876543210']);
    FundingNews::query()->create([
        'kind' => 'hiring', 'headline' => 'Hiring wave in Pune GCCs', 'company' => null,
        'sector' => 'GCC expansion', 'round' => 'Hiring announced', 'hub' => 'Pune', 'hiring_lag_months' => 2,
        'roles' => ['DevOps'], 'skills' => ['Kubernetes'], 'source_name' => 'ET', 'source_url' => null,
        'published_on' => '2026-07-19',
    ]);
    Cache::flush();

    (new SendDailyBrief)->handle(app(Messenger::class));
    (new SendDailyBrief)->handle(app(Messenger::class)); // same day — no resend

    expect(Message::withoutGlobalScopes()->where('template_key', 'daily_brief')->count())->toBe(1);
});
