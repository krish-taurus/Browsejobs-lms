<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MockInterview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Joins the recording parts the room uploaded while the interview was running
 * into the one video file, then points the interview at it. A WebM recorded in
 * timed slices is valid when the slices are simply laid end to end, so this is
 * a concatenation, not a re-encode. If a part is missing it does nothing and
 * the room's full-recording fallback upload (kept in the browser) takes over.
 */
final class AssembleMockRecording implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    public function __construct(public readonly int $mockId, public readonly int $parts) {}

    public static function partsDir(MockInterview $mock): string
    {
        return "mock-recordings/{$mock->tenant_id}/{$mock->id}-parts";
    }

    public function handle(): void
    {
        $mock = MockInterview::query()->withoutGlobalScopes()->find($this->mockId);
        if ($mock === null || $this->parts < 1) {
            return;
        }

        $disk = Storage::disk('s3');
        $dir = self::partsDir($mock);

        for ($i = 0; $i < $this->parts; $i++) {
            if (! $disk->exists("{$dir}/{$i}.part")) {
                return; // A part never arrived: the browser's own full upload covers it.
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rec');
        $out = fopen($tmp, 'wb');
        if ($out === false) {
            return;
        }
        try {
            for ($i = 0; $i < $this->parts; $i++) {
                $in = $disk->readStream("{$dir}/{$i}.part");
                if ($in === null) {
                    return;
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);

            $path = "mock-recordings/{$mock->tenant_id}/{$mock->id}.webm";
            // Never replace a longer recording that is already saved (same rule as the single upload).
            $existing = $mock->recording_url;
            if ($existing !== null && ! str_starts_with($existing, 'http') && $disk->exists($existing) && $disk->size($existing) > (int) filesize($tmp)) {
                return;
            }

            $handle = fopen($tmp, 'rb');
            $disk->put($path, $handle, 'private');
            if (is_resource($handle)) {
                fclose($handle);
            }
            $mock->update(['recording_url' => $path]);

            for ($i = 0; $i < $this->parts; $i++) {
                $disk->delete("{$dir}/{$i}.part");
            }
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }
            @unlink($tmp);
        }
    }
}
