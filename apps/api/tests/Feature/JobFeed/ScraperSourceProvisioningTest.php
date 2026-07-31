<?php

declare(strict_types=1);

use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Support\JobFeed\ApifyTransport;

/**
 * Provisioning and testing scraper sources (PRD §6.22, ADR 0048).
 *
 * The market boards count what the feed ingests, so a source that looks
 * configured and silently returns nothing is the failure mode that matters:
 * the boards then report too little data, correctly, and nobody knows why.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'browsejobs']);
});

it('creates one source per track and does not duplicate on re-run', function (): void {
    $this->artisan('feed:add-scraper', ['board' => 'LinkedIn', 'actor' => 'someone/linkedin-actor'])
        ->assertSuccessful();

    $sources = JobFeedSource::withoutGlobalScopes()->where('kind', JobFeedSource::KIND_SCRAPER)->get();

    expect($sources)->toHaveCount(4)
        ->and($sources->pluck('name')->all())->toContain('LinkedIn — Data Engineering');

    // Re-running with a corrected actor should fix the sources in place.
    // Creating a second set beside them is how a feed quietly doubles its bill.
    $this->artisan('feed:add-scraper', ['board' => 'LinkedIn', 'actor' => 'someone/corrected-actor'])
        ->assertSuccessful();

    $after = JobFeedSource::withoutGlobalScopes()->where('kind', JobFeedSource::KIND_SCRAPER)->get();

    expect($after)->toHaveCount(4)
        ->and($after->first()->config['actor_id'])->toBe('someone/corrected-actor');
});

it('tunes the query per track rather than pulling the same search four times', function (): void {
    $this->artisan('feed:add-scraper', ['board' => 'Naukri', 'actor' => 'someone/naukri-actor'])
        ->assertSuccessful();

    $de = JobFeedSource::withoutGlobalScopes()->where('name', 'Naukri — Data Engineering')->firstOrFail();
    $da = JobFeedSource::withoutGlobalScopes()->where('name', 'Naukri — Data Analytics')->firstOrFail();

    expect($de->config['input']['query'])->toContain('data engineer')
        ->and($da->config['input']['query'])->toContain('data analyst')
        ->and($de->config['exclude'])->toContain('salesforce');
});

it('reports a source that runs and returns nothing, instead of staying silent', function (): void {
    // Exactly what a wrong actor id looks like today: the transport logs a
    // warning, returns [], and the admin screen shows a healthy source.
    $this->mock(ApifyTransport::class, function ($mock): void {
        $mock->shouldReceive('run')->andReturn([]);
    });

    JobFeedSource::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'LinkedIn — Data Engineering',
        'kind' => JobFeedSource::KIND_SCRAPER,
        'config' => ['actor_id' => 'someone/wrong-actor', 'input' => []],
        'priority' => 20,
        'is_active' => true,
    ]);

    $this->artisan('feed:test')
        ->expectsOutputToContain('No source returned a single posting')
        ->assertFailed();
});

it('names a source with no actor configured at all', function (): void {
    JobFeedSource::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Half-configured',
        'kind' => JobFeedSource::KIND_SCRAPER,
        'config' => ['input' => []],
        'priority' => 20,
        'is_active' => true,
    ]);

    $this->artisan('feed:test')
        ->expectsOutputToContain('no actor_id configured')
        ->assertFailed();
});
