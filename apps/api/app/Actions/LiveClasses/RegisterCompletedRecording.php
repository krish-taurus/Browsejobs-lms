<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\RecordingStatus;
use App\Models\LiveSession;
use App\Models\Recording;

/**
 * Registers a completed Zoom Cloud recording so it can be watched on our
 * platform.
 *
 * ONE recording per class. Zoom produces a separate file for each view of the
 * same meeting — "shared_screen_with_speaker_view", "audio_only",
 * "gallery_view" — and this used to key on (session, title), so every view
 * became its own row. Students saw the same class listed two or three times,
 * some copies opening the in-portal player and others bouncing out to Zoom.
 * Keying on the session alone means later views update the same recording
 * instead of multiplying it.
 */
final readonly class RegisterCompletedRecording
{
    /**
     * Below this, a stored recording is almost certainly a stray host
     * test-join before the real class started (Sept 2026 fix) — a host
     * opening the Zoom room to check something minutes early records too,
     * finishes processing on Zoom's side first (small file), and used to
     * permanently lock the class's recording slot before the real hour-long
     * class had even happened. A recording this short never earns that lock.
     */
    public const MIN_REAL_SECONDS = 120;

    public function handle(
        LiveSession $session,
        string $title,
        string $playUrl,
        ?string $passcode = null,
        ?int $durationSeconds = null,
    ): Recording {
        $recording = Recording::query()->firstOrNew(['live_session_id' => $session->id]);

        $recording->fill([
            'tenant_id' => $session->tenant_id,
            'topic_id' => $session->topic_id,
            // Students should read the class name, never Zoom's file-type label.
            'title' => $session->title ?: $title,
            'status' => RecordingStatus::Stored->value,
        ]);

        // A too-short existing recording never earns the "leave it alone"
        // protection a genuine self-hosted copy gets — a later, materially
        // longer recording for the same session is the real class arriving,
        // not just "another view" of what's already there.
        $existingIsStrayTest = $recording->storage_path !== null
            && ($recording->duration_seconds ?? 0) < self::MIN_REAL_SECONDS
            && $durationSeconds !== null
            && $durationSeconds >= self::MIN_REAL_SECONDS;

        // A self-hosted copy plays inside the portal with no passcode, so never
        // trade it back for a Zoom URL just because another view arrived —
        // unless what's stored is that stray short test, being upgraded now.
        if ($recording->storage_path === null || $existingIsStrayTest) {
            $recording->play_url = $playUrl;
            $recording->passcode = $passcode;

            if ($existingIsStrayTest) {
                // The self-hosted bytes on disk are the wrong (tiny) video —
                // clearing storage_path makes SyncZoomRecordings download the
                // real one fresh. Drive carries the same stale file under
                // drive_file_id, so that gets cleared too, ready to re-sync.
                $recording->storage_path = null;
                $recording->size_bytes = null;
                $recording->drive_file_id = null;
                $recording->drive_link = null;
            }
        }

        if ($durationSeconds !== null && ($recording->duration_seconds === null || $existingIsStrayTest)) {
            $recording->duration_seconds = $durationSeconds;
        }

        $recording->save();

        return $recording;
    }
}
