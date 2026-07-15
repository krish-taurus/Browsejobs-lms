<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RecordingStatus;
use App\Models\Recording;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Downloads a completed Zoom recording and stores it in S3/MinIO. Retry-safe:
 * skips work if already stored, and marks the row failed (then rethrows) so the
 * queue can retry.
 */
final class PullRecording implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $recordingId,
        public readonly string $downloadUrl,
    ) {}

    public function handle(ZoomClient $zoom): void
    {
        $recording = Recording::query()->withoutGlobalScopes()->find($this->recordingId);

        if ($recording === null || $recording->status === RecordingStatus::Stored) {
            return;
        }

        try {
            $bytes = $zoom->downloadRecording($this->downloadUrl);
            $path = "recordings/{$recording->tenant_id}/{$recording->live_session_id}/{$recording->id}.mp4";

            Storage::disk('s3')->put($path, $bytes);

            $recording->update([
                'storage_path' => $path,
                'size_bytes' => strlen($bytes),
                'status' => RecordingStatus::Stored->value,
            ]);
        } catch (Throwable $e) {
            $recording->update(['status' => RecordingStatus::Failed->value]);

            throw $e;
        }
    }
}
