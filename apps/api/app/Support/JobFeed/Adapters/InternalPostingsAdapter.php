<?php

declare(strict_types=1);

namespace App\Support\JobFeed\Adapters;

use App\Models\JobFeedSource;
use App\Models\JobPosting;
use App\Support\JobFeed\FeedAdapter;
use App\Support\JobFeed\NormalizedJob;

/**
 * Surfaces the placement team's own open postings (PRD §6.22) into the live feed
 * so internal openings and market roles share one "Jobs for You" surface. Reads
 * from `job_postings`; nothing external.
 */
final class InternalPostingsAdapter implements FeedAdapter
{
    public function kind(): string
    {
        return JobFeedSource::KIND_INTERNAL;
    }

    /**
     * @return list<NormalizedJob>
     */
    public function pull(JobFeedSource $source): array
    {
        return JobPosting::query()
            ->where('status', JobPosting::STATUS_OPEN)
            ->get()
            ->map(fn (JobPosting $p) => new NormalizedJob(
                title: (string) $p->title,
                company: (string) $p->company,
                description: (string) $p->description,
                externalId: 'posting:'.$p->id,
                location: $p->location,
                workMode: $p->work_mode,
                applyUrl: $p->apply_url,
                roleTitle: (string) $p->title,
                postedAt: $p->created_at,
            ))
            ->all();
    }
}
