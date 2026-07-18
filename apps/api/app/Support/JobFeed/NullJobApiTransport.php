<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

/**
 * Default job-API transport: returns nothing. A licensed key isn't configured
 * yet, so the API source is a safe no-op until a real transport (ADR 0045) is
 * bound. Also the binding tests use, so no external call ever happens.
 */
final class NullJobApiTransport implements JobApiTransport
{
    public function search(array $query): array
    {
        return [];
    }
}
