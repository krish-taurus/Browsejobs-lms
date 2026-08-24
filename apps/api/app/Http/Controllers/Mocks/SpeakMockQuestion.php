<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mocks;

use App\Http\Controllers\Controller;
use App\Models\MockInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Reads an interviewer's question aloud in a real voice.
 *
 * The room used the browser's own speech synthesis, which hands the candidate
 * whatever robot their device ships with — a different one on every laptop, and
 * nothing like sitting opposite a person. This proxies ElevenLabs instead.
 *
 * The key never reaches the browser, which is the whole reason this endpoint
 * exists rather than the page calling ElevenLabs directly.
 *
 * With no key configured it answers 204 and the room falls back to the browser
 * voice, so the interview always has a voice even when this does not.
 */
final class SpeakMockQuestion extends Controller
{
    /** Where the generated clips live. Keyed by voice + model + text. */
    private const DISK = 'local';

    private const FOLDER = 'mock-speech';

    /** Clips older than this are swept on the way past. */
    private const KEEP_DAYS = 7;

    public function __invoke(Request $request, int $mock): SymfonyResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:1200'],
        ]);

        // Ownership, the same rule every other mock endpoint uses: you may only
        // speak the questions of an interview that is yours.
        MockInterview::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($mock);

        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = (string) config('services.elevenlabs.voice_id');

        if ($apiKey === '' || $voiceId === '') {
            return response()->noContent();
        }

        $model = (string) config('services.elevenlabs.model', 'eleven_turbo_v2_5');
        $text = trim($validated['text']);

        // The same question asked of the same voice is the same audio, and a
        // repeated question is common — a candidate re-reading the screen, a
        // reconnect, a retake. Paying ElevenLabs twice for it is waste.
        $path = self::FOLDER.'/'.hash('sha256', $voiceId.'|'.$model.'|'.$text).'.mp3';
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => 'audio/mpeg',
            ])->timeout(20)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => $model,
                'voice_settings' => ['stability' => 0.4, 'similarity_boost' => 0.8],
            ]);

            // Never let a TTS outage take the interview down with it — the room
            // falls back to the browser voice on anything but a 200.
            if (! $response->successful()) {
                report(new \RuntimeException('ElevenLabs refused the mock question: '.mb_substr($response->body(), 0, 300)));

                return response()->noContent();
            }

            $disk->put($path, $response->body());
            $this->pruneOldClips($disk);
        }

        return response($disk->get($path), Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Keep the cache from growing forever; it is a convenience, not a record. */
    private function pruneOldClips($disk): void
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();

        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
