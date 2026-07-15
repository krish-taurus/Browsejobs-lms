<?php

declare(strict_types=1);

namespace App\Support\Zoom;

/**
 * Minimal representation of a created Zoom meeting.
 */
final readonly class ZoomMeeting
{
    public function __construct(
        public string $id,
        public string $joinUrl,
        public string $startUrl,
    ) {}
}
