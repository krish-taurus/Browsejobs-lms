<?php

declare(strict_types=1);

namespace App\Actions\JobFeed;

use App\Jobs\ExtractJobFeedItemSkills;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Support\JobFeed\FeedRegistry;
use App\Support\JobFeed\NormalizedJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ingests postings from a source into the live feed (PRD §6.22) through the same
 * pipeline as §6.21: dedupe on a fingerprint, quality-filter thin postings,
 * freshness-stamp an expiry, and queue AI skill extraction per new item. Reusable
 * for both pull adapters and CSV/manual push. Idempotent — re-ingesting the same
 * posting bumps nothing and creates no duplicate.
 */
final readonly class IngestJobFeed
{
    private const DEFAULT_FRESHNESS_DAYS = 30;

    private const MIN_DESCRIPTION = 40;

    public function __construct(private FeedRegistry $registry) {}

    /**
     * Pull + ingest from a configured source.
     *
     * @return array{ingested: int, duplicates: int, skipped: int}
     */
    public function fromSource(JobFeedSource $source): array
    {
        $adapter = $this->registry->for($source->kind);
        $jobs = $adapter !== null ? $adapter->pull($source) : [];

        $summary = $this->push($source, $jobs);
        $source->update(['last_synced_at' => now()]);

        return $summary;
    }

    /**
     * Ingest a batch of already-normalised jobs (used by pull adapters and the
     * CSV/manual importer).
     *
     * @param  list<NormalizedJob>  $jobs
     * @return array{ingested: int, duplicates: int, skipped: int}
     */
    public function push(JobFeedSource $source, array $jobs): array
    {
        $freshnessDays = (int) ($source->config['freshness_days'] ?? self::DEFAULT_FRESHNESS_DAYS);
        $ingested = 0;
        $duplicates = 0;
        $skipped = 0;

        foreach ($jobs as $job) {
            if (mb_strlen(trim($job->description)) < self::MIN_DESCRIPTION || trim($job->title) === '') {
                $skipped++; // spam / too thin to match on

                continue;
            }

            $created = $this->store($source, $job, $freshnessDays);
            $created === null ? $duplicates++ : $ingested++;

            if ($created !== null) {
                ExtractJobFeedItemSkills::dispatch($created->id);
            }
        }

        return ['ingested' => $ingested, 'duplicates' => $duplicates, 'skipped' => $skipped];
    }

    private function store(JobFeedSource $source, NormalizedJob $job, int $freshnessDays): ?JobFeedItem
    {
        $fingerprint = JobFeedItem::fingerprintFor($job->externalId, $job->company, $job->title, $job->location);
        $postedAt = $job->postedAt !== null ? Carbon::instance($job->postedAt) : now();

        return DB::transaction(function () use ($source, $job, $fingerprint, $postedAt, $freshnessDays): ?JobFeedItem {
            $exists = JobFeedItem::query()
                ->where('tenant_id', $source->tenant_id)
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return null;
            }

            return JobFeedItem::query()->create([
                'tenant_id' => $source->tenant_id,
                'job_feed_source_id' => $source->id,
                'external_id' => $job->externalId,
                'title' => mb_substr($job->title, 0, 255),
                'company' => mb_substr($job->company, 0, 255),
                'location' => $job->location !== null ? mb_substr($job->location, 0, 255) : null,
                'work_mode' => $job->workMode,
                'description' => $job->description,
                'apply_url' => $job->applyUrl,
                'source_kind' => $source->kind,
                'role_title' => $job->roleTitle !== null ? mb_substr($job->roleTitle, 0, 255) : null,
                'seniority' => $job->seniority,
                'fingerprint' => $fingerprint,
                'status' => JobFeedItem::STATUS_ACTIVE,
                'posted_at' => $postedAt,
                'expires_at' => $postedAt->copy()->addDays($freshnessDays),
                'ingested_at' => now(),
            ]);
        });
    }
}
