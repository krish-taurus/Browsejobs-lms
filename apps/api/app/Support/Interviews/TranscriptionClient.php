<?php

declare(strict_types=1);

namespace App\Support\Interviews;

/**
 * Swappable speech-to-text transport for audio/video interview uploads
 * (PRD §6.6 ingestion). Bound in AppServiceProvider from services.transcription;
 * FakeTranscriptionClient in tests, NullTranscriptionClient until real
 * credentials (Whisper-compatible or Parakeet API) are configured.
 */
interface TranscriptionClient
{
    /**
     * @param  string  $contents  Raw (decrypted) media bytes.
     *
     * @throws \RuntimeException when transcription is unavailable or fails.
     */
    public function transcribe(string $contents, string $originalName): string;
}
