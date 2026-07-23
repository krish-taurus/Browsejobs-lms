<?php

declare(strict_types=1);

use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\Tenant;

use function Pest\Laravel\getJson;

/**
 * The public job board (ADR 0048): last-7-days openings, tenant by domain,
 * no auth, teaser-only payload.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function boardItem(Tenant $tenant, array $overrides = []): JobFeedItem
{
    return withinTenant($tenant, function () use ($tenant, $overrides) {
        $source = JobFeedSource::factory()->for($tenant)->create();

        return JobFeedItem::factory()->for($tenant)->create(array_merge([
            'job_feed_source_id' => $source->id,
        ], $overrides));
    });
}

it('lists active postings from the last seven days without auth', function () {
    boardItem($this->tenant, ['title' => 'Data Engineer', 'company' => 'FreshCo', 'posted_at' => now()->subDays(2)]);
    boardItem($this->tenant, ['title' => 'Old Role', 'company' => 'StaleCo', 'posted_at' => now()->subDays(9)]);
    boardItem($this->tenant, ['title' => 'Gone Role', 'company' => 'DeadCo', 'posted_at' => now()->subDay(), 'status' => JobFeedItem::STATUS_EXPIRED]);

    $body = getJson('http://acme.test/api/v1/jobs')->assertOk()->json('data');

    expect($body['window_days'])->toBe(7)
        ->and(collect($body['jobs'])->pluck('company')->all())->toBe(['FreshCo']);
});

it('never exposes apply URLs or full descriptions publicly', function () {
    boardItem($this->tenant, [
        'apply_url' => 'https://linkedin.test/j/1',
        'description' => str_repeat('A long JD. ', 200),
        'posted_at' => now()->subDay(),
    ]);

    $job = getJson('http://acme.test/api/v1/jobs')->assertOk()->json('data.jobs.0');

    expect($job)->not->toHaveKey('apply_url')
        ->and(mb_strlen($job['summary']))->toBeLessThanOrEqual(280);
});

it('teases up to three prep questions only when already generated', function () {
    boardItem($this->tenant, ['company' => 'NoPrep', 'posted_at' => now()->subDay()]);
    boardItem($this->tenant, [
        'company' => 'Prepped', 'posted_at' => now()->subHours(2),
        'prep_questions' => [
            ['question' => 'Q1', 'why' => null, 'source' => 'jd'],
            ['question' => 'Q2', 'why' => null, 'source' => 'jd'],
            ['question' => 'Q3', 'why' => null, 'source' => 'jd'],
            ['question' => 'Q4', 'why' => null, 'source' => 'jd'],
        ],
    ]);

    $jobs = collect(getJson('http://acme.test/api/v1/jobs')->assertOk()->json('data.jobs'));

    expect($jobs->firstWhere('company', 'Prepped')['teaser_questions'])->toBe(['Q1', 'Q2', 'Q3'])
        ->and($jobs->firstWhere('company', 'NoPrep')['teaser_questions'])->toBe([]);
});

it('shows only the domain tenant\'s postings', function () {
    $other = Tenant::factory()->domain('other.test')->create();
    boardItem($this->tenant, ['company' => 'MineCo', 'posted_at' => now()->subDay()]);
    boardItem($other, ['company' => 'TheirsCo', 'posted_at' => now()->subDay()]);

    $companies = collect(getJson('http://acme.test/api/v1/jobs')->assertOk()->json('data.jobs'))->pluck('company');

    expect($companies)->toContain('MineCo')->not->toContain('TheirsCo');
});
