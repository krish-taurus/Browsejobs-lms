<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * Bound while no Apify token is configured — scraper sources safely sync to
 * nothing instead of erroring (same pattern as {@see NullJobApiTransport}).
 */
final class NullApifyTransport implements ApifyTransport
{
    public function run(string $actorId, array $input): array
    {
        return [];
    }
}
