<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Models\MockIntegrityEvent;
use App\Models\MockInterview;
use App\Support\Interviews\VoiceTurns;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * After an employer interview, listens to the whole recording once and counts the
 * voices that answered (ElevenLabs speech-to-text with speaker labels). One voice is
 * the normal case. Two voices with real speech each become a `second_voice` finding
 * with the time it started, for the employer's report; either way a `voice_check`
 * measurement is stored so the report can say the check ran.
 *
 * Idempotent: an interview already checked is left alone. Without a recording, a
 * key, or an answer from the service nothing is recorded, and the report says
 * "voice not checked" rather than "clean".
 */
final readonly class AnalyseInterviewVoices
{
    /** Longest recording sent for analysis. */
    private const MAX_BYTES = 150 * 1024 * 1024;

    public function handle(MockInterview $mock): bool
    {
        if ($mock->recording_url === null || str_starts_with($mock->recording_url, 'http')) {
            return false; // No recording of ours (a Zoom bot's lives elsewhere).
        }

        $already = MockIntegrityEvent::query()->withoutGlobalScopes()->where('mock_interview_id', $mock->id)->where('kind', 'voice_check')->exists();
        if ($already) {
            return true;
        }

        $apiKey = (string) config('services.elevenlabs.api_key');
        $disk = Storage::disk('s3');
        if ($apiKey === '' || ! $disk->exists($mock->recording_url) || $disk->size($mock->recording_url) > self::MAX_BYTES) {
            return false;
        }

        $stream = $disk->readStream($mock->recording_url);
        if ($stream === null) {
            return false;
        }

        try {
            $response = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout(150)
                ->attach('file', $stream, 'interview.webm')
                ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                    'model_id' => (string) config('services.elevenlabs.stt_model', 'scribe_v1'),
                    'diarize' => 'true',
                    'timestamps_granularity' => 'word',
                    'tag_audio_events' => 'false',
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            report(new \RuntimeException('ElevenLabs could not analyse an interview recording: '.mb_substr($response->body(), 0, 300)));

            return false;
        }

        $words = collect((array) $response->json('words', []))
            ->filter(fn ($w) => is_array($w) && ($w['type'] ?? 'word') === 'word' && isset($w['speaker_id'], $w['start'], $w['end']))
            ->map(fn (array $w): array => ['speaker' => (string) $w['speaker_id'], 'text' => (string) ($w['text'] ?? ''), 'start' => (float) $w['start'], 'end' => (float) $w['end']])
            ->values()
            ->all();
        if ($words === []) {
            return false;
        }

        $lines = $mock->turns()->withoutGlobalScopes()->where('role', 'interviewer')->pluck('body')->map(fn ($b) => (string) $b)->all();
        $result = VoiceTurns::analyse($words, $lines);

        MockIntegrityEvent::query()->create([
            'tenant_id' => $mock->tenant_id,
            'mock_interview_id' => $mock->id,
            'kind' => 'voice_check',
            'severity' => MockIntegrityEvent::INFO,
            'occurred_at' => now(),
            'detail' => ['voices' => $result['voices'], 'secs_a' => $result['secs_a'], 'secs_b' => $result['secs_b']],
        ]);

        if ($result['voices'] >= 2) {
            MockIntegrityEvent::query()->create([
                'tenant_id' => $mock->tenant_id,
                'mock_interview_id' => $mock->id,
                'kind' => 'second_voice',
                'severity' => MockIntegrityEvent::POSSIBLE,
                'elapsed_seconds' => $result['first_b_at'],
                'occurred_at' => now(),
                'detail' => ['secs' => $result['secs_b'], 'voices' => $result['voices']],
            ]);
        }

        return true;
    }
}
