<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * Transport for a licensed job API (PRD §6.22 — JSearch, chosen in ADR 0045).
 * Swappable so the ingestion pipeline never depends on a vendor's shape, and so
 * tests bind a fake (external APIs are never called in tests — CLAUDE.md).
 */
interface JobApiTransport
{
    /**
     * Fetch raw postings for a query. Must never throw on network/quota errors —
     * return an empty array so a source outage degrades to "no new items".
     *
     * @param  array<string, mixed>  $query
     * @return list<NormalizedJob>
     */
    public function search(array $query): array;
}
