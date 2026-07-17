<?php

declare(strict_types=1);

namespace App\Support\Interviews;

use RuntimeException;

/**
 * Default transport when no speech-to-text provider is configured: the upload
 * is preserved (encrypted) and the transcript is marked failed with a clear
 * operator message instead of pretending to transcribe.
 */
final class NullTranscriptionClient implements TranscriptionClient
{
    public function transcribe(string $contents, string $originalName): string
    {
        throw new RuntimeException(
            'No transcription provider configured — set services.transcription and re-queue, or upload the transcript as text.',
        );
    }
}
