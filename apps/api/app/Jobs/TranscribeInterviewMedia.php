<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterviewTranscript;
use App\Support\Interviews\Anonymizer;
use App\Support\Interviews\TranscriptionClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Speech-to-text stage for audio/video interview uploads (PRD §6.6): decrypt
 * the stored original, transcribe via the swappable client, scrub, then hand
 * off to the parsing stage. Failure keeps the encrypted original and reports
 * why — nothing is silently lost.
 */
final class TranscribeInterviewMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly int $transcriptId) {}

    public function handle(TranscriptionClient $client, Anonymizer $anonymizer): void
    {
        $transcript = InterviewTranscript::query()->withoutGlobalScopes()->find($this->transcriptId);
        if ($transcript === null || $transcript->encrypted_path === null) {
            return;
        }

        try {
            $contents = Crypt::decryptString((string) Storage::disk('s3')->get($transcript->encrypted_path));
            $text = $client->transcribe($contents, (string) $transcript->original_name);
        } catch (Throwable $e) {
            $transcript->update([
                'status' => InterviewTranscript::STATUS_FAILED,
                'parse_error' => mb_substr($e->getMessage(), 0, 480),
            ]);

            return;
        }

        $transcript->update([
            'raw_text' => $anonymizer->scrub($text),
            'status' => InterviewTranscript::STATUS_PARSING,
        ]);

        ParseInterviewTranscript::dispatch($transcript->id);
    }
}
