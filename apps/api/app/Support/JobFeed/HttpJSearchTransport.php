<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live job-feed transport backed by the JSearch API on RapidAPI (PRD §6.22,
 * ADR 0045). JSearch legally aggregates postings that employers publish to
 * Google for Jobs — LinkedIn, Indeed, Naukri, Glassdoor, company ATS — so those
 * roles reach the BrowseJobs feed WITHOUT scraping any portal. Maps each result
 * to a source-agnostic {@see NormalizedJob}; the existing ingestion pipeline then
 * dedupes, skill-extracts, and freshness-tracks it like any other source.
 *
 * Never throws on a network/quota error — returns [] so a source outage degrades
 * to "no new items" rather than breaking the sync. Tests fake the HTTP client;
 * no real call is ever made in tests.
 */
final readonly class HttpJSearchTransport implements JobApiTransport
{
    /**
     * @param  array{api_key: string, host: string}  $config
     */
    public function __construct(private array $config) {}

    /**
     * @param  array<string, mixed>  $query
     * @return list<NormalizedJob>
     */
    public function search(array $query): array
    {
        $q = trim((string) ($query['query'] ?? 'software developer in india'));

        try {
            $response = Http::withHeaders([
                'X-RapidAPI-Key' => $this->config['api_key'],
                'X-RapidAPI-Host' => $this->config['host'],
            ])->timeout(20)->get("https://{$this->config['host']}/search", [
                'query' => $q,
                'page' => (string) ($query['page'] ?? 1),
                'num_pages' => (string) ($query['num_pages'] ?? 1),
                'date_posted' => (string) ($query['date_posted'] ?? 'week'),
                'country' => (string) ($query['country'] ?? 'in'),
            ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('data');
        } catch (Throwable) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $jobs = [];
        foreach ($data as $row) {
            $job = $this->map(is_array($row) ? $row : []);
            if ($job !== null) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function map(array $row): ?NormalizedJob
    {
        $title = trim((string) ($row['job_title'] ?? ''));
        $company = trim((string) ($row['employer_name'] ?? ''));
        $description = trim((string) ($row['job_description'] ?? ''));

        if ($title === '' || $company === '' || $description === '') {
            return null;
        }

        $location = trim(implode(', ', array_filter([
            $row['job_city'] ?? null,
            $row['job_state'] ?? null,
        ], fn ($v) => is_string($v) && $v !== ''))) ?: null;

        return new NormalizedJob(
            title: $title,
            company: $company,
            description: $description,
            externalId: isset($row['job_id']) ? 'jsearch:'.$row['job_id'] : null,
            location: $location,
            workMode: ($row['job_is_remote'] ?? false) === true ? 'remote' : null,
            applyUrl: is_string($row['job_apply_link'] ?? null) ? $row['job_apply_link'] : null,
            roleTitle: $title,
            postedAt: $this->parseDate($row['job_posted_at_datetime_utc'] ?? null),
        );
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
