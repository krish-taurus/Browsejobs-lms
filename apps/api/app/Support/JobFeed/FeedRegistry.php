<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Support\JobFeed\Adapters\InternalPostingsAdapter;
use App\Support\JobFeed\Adapters\LicensedApiAdapter;

/**
 * Resolves a source's `kind` to its adapter (PRD §6.22). Partner and ATS sources
 * are the same pattern — register their adapters here as they land (ADR 0045);
 * CSV/manual sources are ingested directly by the admin importer, not pulled.
 */
final class FeedRegistry
{
    public function __construct(
        private readonly InternalPostingsAdapter $internal,
        private readonly LicensedApiAdapter $api,
    ) {}

    public function for(string $kind): ?FeedAdapter
    {
        return match ($kind) {
            $this->internal->kind() => $this->internal,
            $this->api->kind() => $this->api,
            default => null, // csv/manual are pushed, not pulled
        };
    }
}
