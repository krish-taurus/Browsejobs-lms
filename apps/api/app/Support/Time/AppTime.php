<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Parses external timestamps (webhook payloads, API inputs, fixtures) into the
 * app timezone. Eloquent's datetime casts store a Carbon's own wall time and
 * read it back in the app timezone, so a UTC-zoned instance saved under an IST
 * app timezone silently shifts by the offset. Converting at the boundary keeps
 * the stored instant correct under any APP_TIMEZONE.
 */
final class AppTime
{
    public static function parse(DateTimeInterface|string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }
}
