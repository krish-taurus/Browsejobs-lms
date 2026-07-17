<?php

declare(strict_types=1);

namespace App\Support\Zoom;

use Carbon\CarbonInterface;

/**
 * In-memory Zoom client for tests and local dev without real credentials.
 * Records calls and returns deterministic meeting data.
 */
final class FakeZoomClient implements ZoomClient
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<array<string, mixed>> */
    public array $updated = [];

    private int $sequence = 0;

    public string $recordingBytes = 'fake-recording-bytes';

    public function createMeeting(
        string $topic,
        CarbonInterface $start,
        int $durationMinutes,
        ?string $hostUserId = null,
        ?bool $autoRecord = null,
    ): ZoomMeeting {
        $this->sequence++;
        $id = (string) (900_000_000 + $this->sequence);
        $this->created[] = [
            'id' => $id, 'topic' => $topic, 'start' => $start, 'duration' => $durationMinutes,
            'host' => $hostUserId, 'auto_record' => $autoRecord,
        ];

        return new ZoomMeeting(
            id: $id,
            joinUrl: "https://zoom.test/j/{$id}",
            startUrl: "https://zoom.test/s/{$id}",
        );
    }

    public function updateMeeting(string $meetingId, CarbonInterface $start, int $durationMinutes): void
    {
        $this->updated[] = ['id' => $meetingId, 'start' => $start, 'duration' => $durationMinutes];
    }

    public function deleteMeeting(string $meetingId): void
    {
        $this->deleted[] = $meetingId;
    }

    public function downloadRecording(string $downloadUrl): string
    {
        return $this->recordingBytes;
    }
}
