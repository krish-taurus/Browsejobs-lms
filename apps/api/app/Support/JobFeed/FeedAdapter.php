<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Models\JobFeedSource;

/**
 * The single `JobFeedSource` adapter interface (PRD §6.22). Every source —
 * internal postings, hiring-partner feeds, a licensed job API, public ATS
 * endpoints, CSV — implements this so new sources plug in without touching
 * ingestion. NO raw portal scraping: adapters only wrap authorised feeds/APIs.
 */
interface FeedAdapter
{
    /** The JobFeedSource::KIND_* this adapter handles. */
    public function kind(): string;

    /**
     * Pull the current postings for a configured source, normalised. Adapters
     * must never throw on empty/unreachable feeds — return an empty array.
     *
     * @return list<NormalizedJob>
     */
    public function pull(JobFeedSource $source): array;
}
