<?php

declare(strict_types=1);

use App\Models\JobFeedItem;
use App\Models\Tenant;
use App\Support\Market\FeedMarketIntel;

/**
 * Market figures counted from ingested postings (PRD §6.19).
 *
 * These replaced numbers a model produced — the prompt asked for estimates
 * that were "order-of-magnitude correct" and the page badged the answer as
 * live market analysis. The tests that matter are the ones about refusing to
 * answer: a real count over a thin sample is still not a measurement, and
 * printing it would repeat the original problem with extra steps.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    config(['market_intel.min_sample' => 10, 'market_intel.min_skill_sample' => 10, 'market_intel.window_days' => 30]);

    // These assert on exact shares, and the snapshot is platform-wide by
    // design, so any feed rows a seeder left behind would land inside the
    // windows and move the arithmetic. Start from an empty feed.
    JobFeedItem::withoutGlobalScopes()->delete();
});

function posting(mixed $test, array $attributes = []): JobFeedItem
{
    return JobFeedItem::factory()->for($test->tenant)->create(array_merge([
        'role_title' => 'Data Engineer',
        'title' => 'Data Engineer',
        'location' => 'Bengaluru',
        'extracted_skills' => ['spark', 'sql'],
        'source_kind' => 'api',
        'status' => JobFeedItem::STATUS_ACTIVE,
        'posted_at' => now()->subDays(5),
    ], $attributes));
}

it('says it does not have enough postings rather than printing a thin number', function (): void {
    // Nine postings is a real count and still not a market signal. The old
    // surface would have shown "≈18,000 openings" regardless.
    for ($i = 0; $i < 9; $i++) {
        posting($this);
    }

    $snapshot = app(FeedMarketIntel::class)->snapshot();

    expect($snapshot['sufficient'])->toBeFalse()
        ->and($snapshot['tracks']['data-engineering']['sufficient'])->toBeFalse()
        ->and($snapshot['tracks']['data-engineering'])->not->toHaveKey('trend_pct')
        // The count is still returned so a surface can say "12 so far"
        // instead of implying the market is empty.
        ->and($snapshot['tracks']['data-engineering']['total'])->toBe(9);
});

it('counts real postings per track once there are enough', function (): void {
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['location' => $i < 8 ? 'Bengaluru' : 'Pune']);
    }
    for ($i = 0; $i < 11; $i++) {
        posting($this, ['role_title' => 'DevOps Engineer', 'title' => 'DevOps Engineer', 'extracted_skills' => ['kubernetes', 'terraform']]);
    }

    $snapshot = app(FeedMarketIntel::class)->snapshot();
    $de = $snapshot['tracks']['data-engineering'];

    expect($snapshot['sufficient'])->toBeTrue()
        ->and($de['sufficient'])->toBeTrue()
        ->and($de['total'])->toBe(12)
        ->and($de['cities'][0]['name'])->toBe('Bengaluru')
        ->and($de['cities'][0]['share'])->toBe(67)
        ->and($snapshot['tracks']['devops-cloud']['total'])->toBe(11);
});

it('reports skill movement as a share, so a bigger feed is not read as more demand', function (): void {
    // Prior window: 12 postings, all Spark.
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(45), 'extracted_skills' => ['spark']]);
    }
    // Current window: twice the postings, but Spark is now half of them.
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(5), 'extracted_skills' => ['spark']]);
    }
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(5), 'extracted_skills' => ['dbt']]);
    }

    $snapshot = app(FeedMarketIntel::class)->snapshot();
    $spark = collect([...$snapshot['rising'], ...$snapshot['cooling']])->firstWhere('skill', 'spark');

    // Spark's raw count doubled. Its share halved, and share is what demand
    // means — counting raw would have called a bigger feed a hiring boom.
    expect($spark)->not->toBeNull()
        ->and($spark['change_pct'])->toBeLessThan(0);
});

it('ignores a skill too rare in either window to mean anything', function (): void {
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(45), 'extracted_skills' => ['spark']]);
    }
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(5), 'extracted_skills' => ['spark']]);
    }
    // Two mentions becoming four is +100% and is noise.
    posting($this, ['posted_at' => now()->subDays(45), 'extracted_skills' => ['cobol']]);
    posting($this, ['posted_at' => now()->subDays(45), 'extracted_skills' => ['cobol']]);
    for ($i = 0; $i < 4; $i++) {
        posting($this, ['posted_at' => now()->subDays(5), 'extracted_skills' => ['cobol']]);
    }

    $snapshot = app(FeedMarketIntel::class)->snapshot();
    $skills = collect([...$snapshot['rising'], ...$snapshot['cooling']])->pluck('skill');

    expect($skills)->not->toContain('cobol');
});

it('reports no movement at all when a window is too thin to compare', function (): void {
    for ($i = 0; $i < 12; $i++) {
        posting($this, ['posted_at' => now()->subDays(5)]);
    }

    // Nothing in the prior window: there is no comparison to make, and
    // inventing a baseline of zero would report every skill as infinitely up.
    $snapshot = app(FeedMarketIntel::class)->snapshot();

    expect($snapshot['rising'])->toBe([])
        ->and($snapshot['cooling'])->toBe([]);
});

it('serves the counted figures on the public endpoint', function (): void {
    for ($i = 0; $i < 12; $i++) {
        posting($this);
    }

    $this->getJson('/api/v1/market-intel')
        ->assertOk()
        ->assertJsonPath('data.feed.sufficient', true)
        ->assertJsonPath('data.feed.tracks.data-engineering.total', 12);
});
