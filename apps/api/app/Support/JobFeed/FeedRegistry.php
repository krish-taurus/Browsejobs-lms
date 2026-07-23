<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Support\JobFeed\Adapters\InternalPostingsAdapter;
use App\Support\JobFeed\Adapters\LicensedApiAdapter;
use App\Support\JobFeed\Adapters\ScraperAdapter;

/**
 * Resolves a source's `kind` to its adapter (PRD §6.22). Partner and ATS sources
 * are the same pattern — register their adapters here as they land (ADR 0045);
 * CSV/manual sources are ingested directly by the admin importer, not pulled.
 * Scraper sources (Apify actors) are ADR 0048's founder-approved addition.
 */
final class FeedRegistry
{
    public function __construct(
        private readonly InternalPostingsAdapter $internal,
        private readonly LicensedApiAdapter $api,
        private readonly ScraperAdapter $scraper,
    ) {}

    public function for(string $kind): ?FeedAdapter
    {
        return match ($kind) {
            $this->internal->kind() => $this->internal,
            $this->api->kind() => $this->api,
            $this->scraper->kind() => $this->scraper,
            default => null, // csv/manual are pushed, not pulled
        };
    }
}
