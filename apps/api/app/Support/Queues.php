<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Names of the queues, and what each one is for. One worker used to serve a single
 * queue, so a 3-hour burst of slow work (the Monday weekly reports) stood in front of
 * everything else, including the code a student is waiting for to log in.
 *
 *  - HIGH    a person is waiting right now (login codes). Always served first.
 *  - DEFAULT everything else, fast jobs (message sends, reminders, Zoom, interview steps).
 *  - BULK    slow, high-volume jobs nobody is waiting on (weekly reports). Own worker.
 */
final class Queues
{
    public const HIGH = 'high';

    public const DEFAULT = 'default';

    public const BULK = 'bulk';

    /** Templates a person is actively waiting for: delivered ahead of everything else. */
    private const URGENT_TEMPLATES = ['otp'];

    public static function forTemplate(?string $templateKey): string
    {
        return in_array($templateKey, self::URGENT_TEMPLATES, true) ? self::HIGH : self::DEFAULT;
    }
}
