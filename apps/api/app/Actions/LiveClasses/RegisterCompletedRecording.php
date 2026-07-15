<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\RecordingStatus;
use App\Jobs\PullRecording;
use App\Models\LiveSession;
use App\Models\Recording;

/**
 * Records a completed Zoom recording and queues the job that pulls the file
 * into S3/MinIO. Idempotent per session+title so duplicate webhooks don't
 * create duplicate rows.
 */
final readonly class RegisterCompletedRecording
{
    public function handle(LiveSession $session, string $title, string $downloadUrl, ?int $durationSeconds = null): Recording
    {
        $recording = Recording::query()->firstOrCreate(
            ['live_session_id' => $session->id, 'title' => $title],
            [
                'tenant_id' => $session->tenant_id,
                'topic_id' => $session->topic_id,
                'duration_seconds' => $durationSeconds,
                'status' => RecordingStatus::Pending->value,
            ],
        );

        if ($recording->wasRecentlyCreated) {
            PullRecording::dispatch($recording->id, $downloadUrl);
        }

        return $recording;
    }
}
