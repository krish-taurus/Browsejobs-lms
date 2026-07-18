<?php

declare(strict_types=1);

use App\Support\JobFeed\HttpJSearchTransport;
use Illuminate\Support\Facades\Http;

// External APIs are never really called in tests (CLAUDE.md) — Http::fake stands in.

it('maps a JSearch API response into normalised feed jobs', function () {
    Http::fake([
        'jsearch.p.rapidapi.com/*' => Http::response([
            'status' => 'OK',
            'data' => [
                [
                    'job_id' => 'abc123',
                    'job_title' => 'Data Engineer',
                    'employer_name' => 'Acme Corp',
                    'job_description' => 'Build ETL pipelines with Python, SQL and Airflow on AWS.',
                    'job_city' => 'Bengaluru',
                    'job_state' => 'Karnataka',
                    'job_is_remote' => false,
                    'job_apply_link' => 'https://example.com/apply/abc123',
                    'job_posted_at_datetime_utc' => '2026-07-15T09:00:00.000Z',
                ],
                ['job_title' => '', 'employer_name' => '', 'job_description' => ''], // junk row → dropped
            ],
        ], 200),
    ]);

    $transport = new HttpJSearchTransport(['api_key' => 'test-key', 'host' => 'jsearch.p.rapidapi.com']);
    $jobs = $transport->search(['query' => 'data engineer in india']);

    expect($jobs)->toHaveCount(1);
    $job = $jobs[0];
    expect($job->title)->toBe('Data Engineer')
        ->and($job->company)->toBe('Acme Corp')
        ->and($job->externalId)->toBe('jsearch:abc123')
        ->and($job->location)->toBe('Bengaluru, Karnataka')
        ->and($job->applyUrl)->toBe('https://example.com/apply/abc123')
        ->and($job->postedAt)->not->toBeNull();
});

it('returns an empty list on an API error instead of throwing', function () {
    Http::fake(['jsearch.p.rapidapi.com/*' => Http::response('rate limited', 429)]);

    $transport = new HttpJSearchTransport(['api_key' => 'test-key', 'host' => 'jsearch.p.rapidapi.com']);

    expect($transport->search(['query' => 'x']))->toBe([]);
});

it('sends the RapidAPI auth headers', function () {
    Http::fake(['jsearch.p.rapidapi.com/*' => Http::response(['data' => []], 200)]);

    (new HttpJSearchTransport(['api_key' => 'secret-key', 'host' => 'jsearch.p.rapidapi.com']))
        ->search(['query' => 'qa engineer']);

    Http::assertSent(fn ($request) => $request->hasHeader('X-RapidAPI-Key', 'secret-key')
        && str_contains($request->url(), 'query=qa'));
});
