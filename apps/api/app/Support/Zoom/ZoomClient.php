<?php

declare(strict_types=1);

namespace App\Support\Zoom;

use Carbon\CarbonInterface;

/**
 * Zoom Server-to-Server API. Every implementation is called only from queued
 * jobs (CLAUDE.md: Zoom API calls are queued). Tests bind a fake.
 */
interface ZoomClient
{
    public function createMeeting(string $topic, CarbonInterface $start, int $durationMinutes): ZoomMeeting;

    public function updateMeeting(string $meetingId, CarbonInterface $start, int $durationMinutes): void;

    public function deleteMeeting(string $meetingId): void;

    /**
     * Download a recording file and return its raw bytes.
     */
    public function downloadRecording(string $downloadUrl): string;
}
