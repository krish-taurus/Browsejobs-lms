<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mocks;

use App\Http\Controllers\Controller;
use App\Models\MockInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Turns a spoken answer into text for browsers without speech recognition.
 *
 * The room transcribes answers with the browser's own SpeechRecognition, which
 * only Chrome and Edge ship — Firefox, Safari and Brave candidates could only
 * type. In those browsers the room records the answer from the microphone it
 * already holds and posts the clip here; ElevenLabs transcribes it and the room
 * sends the text as the answer, exactly like a Chrome-transcribed one.
 *
 * The key never reaches the browser. The clip is not stored — the interview
 * recording already captures the call — it is only passed through.
 *
 * With no key configured it answers 503 and the room offers typing instead.
 */
final class TranscribeMockAnswer extends Controller
{
    public function __invoke(Request $request, int $mock): JsonResponse
    {
        $request->validate([
            // ~3 minutes of opus audio is well under this; the room stops at 3.
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        // Ownership, the same rule every other mock endpoint uses — and only
        // while the interview is still running.
        $interview = MockInterview::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($mock);

        if ($interview->status !== MockInterview::STATUS_IN_PROGRESS) {
            return response()->json(['error' => ['code' => 'closed', 'message' => 'This interview has ended.']], 409);
        }

        $file = $request->file('audio');
        $mime = (string) $file->getMimeType();
        // Firefox records Ogg (sniffed as audio/ogg or application/ogg), Chrome
        // and Firefox WebM (often sniffed as video/webm), Safari MP4.
        if (! str_starts_with($mime, 'audio/') && ! str_starts_with($mime, 'video/') && $mime !== 'application/ogg') {
            return response()->json(['error' => ['code' => 'not_audio', 'message' => 'That upload was not audio.']], 422);
        }

        $apiKey = (string) config('services.elevenlabs.api_key');
        if ($apiKey === '') {
            return $this->unavailable();
        }

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(45)
            ->attach('file', (string) file_get_contents($file->getRealPath()), 'answer.'.($file->guessExtension() ?: 'webm'))
            ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                'model_id' => (string) config('services.elevenlabs.stt_model', 'scribe_v1'),
                'language_code' => (string) config('services.elevenlabs.stt_language', 'en'),
                // Plain words only — no "(laughs)" or "(background noise)" tags
                // ending up in the answer the interviewer grades.
                'tag_audio_events' => 'false',
            ]);

        if (! $response->successful()) {
            report(new \RuntimeException('ElevenLabs could not transcribe a mock answer: '.mb_substr($response->body(), 0, 300)));

            return $this->unavailable();
        }

        return response()->json(['data' => ['text' => trim((string) $response->json('text', ''))]]);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'stt_unavailable', 'message' => "Voice input isn't available right now — type your answer instead."],
        ], 503);
    }
}
