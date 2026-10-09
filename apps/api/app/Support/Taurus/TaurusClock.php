<?php

declare(strict_types=1);

namespace App\Support\Taurus;

use Carbon\CarbonImmutable;

/**
 * "Today" on the Taurus console is a calendar day in taurus.timezone (IST by
 * default), not the server's UTC day — "done today" at 9am in Bengaluru must
 * not include yesterday evening's work. Returns bounds in the app timezone so
 * they compare directly against stored timestamps.
 */
final class TaurusClock
{
    public static function timezone(): string
    {
        return (string) config('taurus.timezone', 'Asia/Kolkata');
    }

    /** Start of the local day, expressed in the app timezone. */
    public static function startOfToday(): CarbonImmutable
    {
        return self::startOfDaysAgo(0);
    }

    /** Start of the local day N days ago, expressed in the app timezone. */
    public static function startOfDaysAgo(int $days): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())
            ->subDays($days)
            ->startOfDay()
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }

    /** The local calendar date (YYYY-MM-DD) a stored timestamp falls on. */
    public static function localDate(\DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone())->toDateString();
    }
}
