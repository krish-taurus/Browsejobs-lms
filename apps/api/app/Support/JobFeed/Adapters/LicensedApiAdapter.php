<?php

declare(strict_types=1);

namespace App\Support\JobFeed\Adapters;

use App\Models\JobFeedSource;
use App\Support\JobFeed\FeedAdapter;
use App\Support\JobFeed\JobApiTransport;
use App\Support\JobFeed\NormalizedJob;

/**
 * Licensed job-API source (PRD §6.22; JSearch per ADR 0045). Delegates the actual
 * call to a swappable {@see JobApiTransport} — Null by default (no key yet), a
 * fake in tests. The source's `config` supplies the query (role, location, etc).
 */
final readonly class LicensedApiAdapter implements FeedAdapter
{
    public function __construct(private JobApiTransport $transport) {}

    public function kind(): string
    {
        return JobFeedSource::KIND_API;
    }

    /**
     * @return list<NormalizedJob>
     */
    public function pull(JobFeedSource $source): array
    {
        $query = (array) ($source->config['query'] ?? []);

        return $this->transport->search($query);
    }
}
