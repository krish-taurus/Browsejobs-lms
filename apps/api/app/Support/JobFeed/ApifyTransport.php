<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * Runs an Apify actor and returns its dataset items (ADR 0048). Swappable so
 * the scraper adapter never depends on Apify's HTTP shape; a fake in tests —
 * no external call ever happens in CI. The platform token comes from the
 * admin Settings screen (or APIFY_TOKEN); a source may carry its own override.
 */
interface ApifyTransport
{
    /**
     * Run the actor synchronously and return its dataset items as raw arrays.
     * Implementations must never throw on failure — return an empty array.
     *
     * @param  array<string, mixed>  $input  the actor's input document
     * @param  string|null  $token  per-source token override; null = platform token
     * @return list<array<string, mixed>>
     */
    public function run(string $actorId, array $input, ?string $token = null): array;
}
