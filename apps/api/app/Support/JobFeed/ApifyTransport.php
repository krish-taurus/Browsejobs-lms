<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * Runs an Apify actor and returns its dataset items (ADR 0048). Swappable so
 * the scraper adapter never depends on Apify's HTTP shape: Null when no token
 * is configured, a fake in tests — no external call ever happens in CI.
 */
interface ApifyTransport
{
    /**
     * Run the actor synchronously and return its dataset items as raw arrays.
     * Implementations must never throw on failure — return an empty array.
     *
     * @param  array<string, mixed>  $input  the actor's input document
     * @return list<array<string, mixed>>
     */
    public function run(string $actorId, array $input): array;
}
