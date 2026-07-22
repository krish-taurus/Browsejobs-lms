<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\RecordingStatus;
use App\Models\LiveSession;
use App\Models\Recording;

/**
 * Registers a completed Zoom Cloud recording so it can be watched on our platform
 * WITHOUT importing the file into our storage: we store Zoom's play URL (+ passcode)
 * and stream from Zoom Cloud. Idempotent per session+title so duplicate webhooks
 * don't create duplicate rows.
 */
final readonly class RegisterCompletedRecording
{
    public function handle(
        LiveSession $session,
        string $title,
        string $playUrl,
        ?string $passcode = null,
        ?int $durationSeconds = null,
    ): Recording {
        $recording = Recording::query()->firstOrCreate(
            ['live_session_id' => $session->id, 'title' => $title],
            [
                'tenant_id' => $session->tenant_id,
                'topic_id' => $session->topic_id,
                'play_url' => $playUrl,
                'passcode' => $passcode,
                'duration_seconds' => $durationSeconds,
                'status' => RecordingStatus::Stored->value,
            ],
        );

        // Backfill the play URL if an earlier (pending/legacy) row already existed.
        if (! $recording->wasRecentlyCreated && $recording->play_url === null) {
            $recording->update([
                'play_url' => $playUrl,
                'passcode' => $passcode,
                'status' => RecordingStatus::Stored->value,
            ]);
        }

        return $recording;
    }
}
