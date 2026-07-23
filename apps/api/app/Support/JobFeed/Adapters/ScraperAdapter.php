<?php

declare(strict_types=1);

namespace App\Support\JobFeed\Adapters;

use App\Models\JobFeedSource;
use App\Support\JobFeed\ApifyTransport;
use App\Support\JobFeed\FeedAdapter;
use App\Support\JobFeed\NormalizedJob;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Scraper source via Apify actors (ADR 0048 — explicit founder override of the
 * PRD's no-scraping rule; Naukri + LinkedIn to start). The source's `config`
 * carries `actor_id` (e.g. "apify/linkedin-jobs-scraper") and an `input`
 * document passed to the actor verbatim, so swapping actors or tuning search
 * terms is admin configuration, not code. Field names differ per actor, so
 * mapping is tolerant: first non-empty of the known aliases wins.
 */
final readonly class ScraperAdapter implements FeedAdapter
{
    /** Scraped postings age fast — default freshness window in days. */
    public const DEFAULT_FRESHNESS_DAYS = 7;

    public function __construct(private ApifyTransport $apify) {}

    public function kind(): string
    {
        return JobFeedSource::KIND_SCRAPER;
    }

    /**
     * @return list<NormalizedJob>
     */
    public function pull(JobFeedSource $source): array
    {
        $actorId = trim((string) ($source->config['actor_id'] ?? ''));
        if ($actorId === '') {
            return [];
        }

        $input = $this->tightenQuery((array) ($source->config['input'] ?? []), (array) $source->config);

        $jobs = [];
        foreach ($this->apify->run($actorId, $input) as $raw) {
            $job = $this->normalize($raw);
            if ($job !== null) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * Tighten the actor's search query before spending money on a run: a plain
     * role name becomes an exact phrase ("Data engineer" instead of fuzzy
     * keyword matching, which returns Salesforce and frontend roles), and the
     * source's `exclude` terms are appended as NOT clauses. A query the admin
     * already wrote with quotes or AND/OR/NOT boolean syntax is left alone.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function tightenQuery(array $input, array $config): array
    {
        // Actors name the search field differently: `query` (LinkedIn),
        // `keyword` (Naukri), `search`. Tighten whichever ones are present.
        foreach (['query', 'keyword', 'search'] as $field) {
            $query = $input[$field] ?? null;
            if (! is_string($query) || trim($query) === '') {
                continue;
            }

            $query = trim($query);
            if (! str_contains($query, '"') && preg_match('/\b(AND|OR|NOT)\b/', $query) !== 1) {
                $query = '"'.$query.'"';
            }

            foreach ((array) ($config['exclude'] ?? []) as $term) {
                $term = trim((string) $term);
                if ($term !== '') {
                    $query .= ' NOT '.(str_contains($term, ' ') ? '"'.$term.'"' : $term);
                }
            }

            $input[$field] = $query;
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function normalize(array $raw): ?NormalizedJob
    {
        $title = $this->str($raw, ['title', 'jobTitle', 'position', 'name']);
        $company = $this->str($raw, ['company', 'companyName', 'company_name', 'employer', 'hiringOrganization']);
        $description = $this->str($raw, ['description', 'jobDescription', 'descriptionText', 'description_text', 'descriptionHtml', 'details']);

        if ($title === null || $company === null || $description === null) {
            return null; // Not a job row this adapter can use.
        }

        return new NormalizedJob(
            title: $title,
            company: $company,
            description: strip_tags($description),
            externalId: $this->str($raw, ['id', 'jobId', 'job_id', 'jobkey', 'listingId']),
            location: $this->str($raw, ['location', 'jobLocation', 'place', 'city']),
            workMode: $this->workMode($raw),
            applyUrl: $this->str($raw, ['url', 'link', 'jobUrl', 'job_url', 'applyUrl', 'apply_url']),
            roleTitle: $this->str($raw, ['roleTitle', 'occupation']) ?? $title,
            seniority: $this->str($raw, ['seniority', 'seniorityLevel', 'experienceLevel']),
            postedAt: $this->postedAt($raw),
            skills: $this->skills($raw),
        );
    }

    /**
     * Skills many actors pre-extract (e.g. LinkedIn's `skills` array) — passed
     * through so items are matchable immediately without an AI extraction call.
     *
     * @param  array<string, mixed>  $raw
     * @return list<string>|null
     */
    private function skills(array $raw): ?array
    {
        $value = $raw['skills'] ?? $raw['skillsRequired'] ?? null;
        if (! is_array($value)) {
            return null;
        }

        $skills = [];
        foreach ($value as $skill) {
            $clean = mb_strtolower(trim((string) $skill));
            if ($clean !== '' && mb_strlen($clean) <= 40) {
                $skills[] = $clean;
            }
        }

        $skills = array_values(array_unique(array_slice($skills, 0, 20)));

        return $skills === [] ? null : $skills;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    private function str(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function workMode(array $raw): ?string
    {
        $mode = $this->str($raw, ['workMode', 'work_mode', 'workplaceType', 'jobType']);
        if ($mode !== null) {
            $mode = mb_strtolower($mode);

            return match (true) {
                str_contains($mode, 'remote') => 'remote',
                str_contains($mode, 'hybrid') => 'hybrid',
                str_contains($mode, 'on-site'), str_contains($mode, 'onsite'), str_contains($mode, 'office') => 'onsite',
                default => null,
            };
        }

        return ($raw['remote'] ?? null) === true ? 'remote' : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function postedAt(array $raw): ?Carbon
    {
        $value = $this->str($raw, ['postedAt', 'posted_at', 'postedDate', 'publishedAt', 'listedAt', 'datePosted', 'createdAt']);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null; // Relative strings like "3 days ago" → treat as unknown.
        }
    }
}
