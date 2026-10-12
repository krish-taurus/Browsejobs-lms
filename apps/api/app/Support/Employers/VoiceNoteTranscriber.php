<?php

declare(strict_types=1);

namespace App\Support\Employers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns a client's WhatsApp voice note into text, so a job can be posted by
 * just speaking it. Downloads the media from Meta (media id → short-lived
 * URL → bytes, both with the WhatsApp token) and sends it to ElevenLabs
 * speech-to-text — the same vendor and key the hiring console's microphone
 * already uses. No language is forced: clients speak English, Hindi or a mix.
 *
 * Returns null on any failure; the agent then asks the client to type.
 */
final class VoiceNoteTranscriber
{
    /** WhatsApp voice notes are small; anything bigger is not a voice note. */
    private const MAX_BYTES = 16 * 1024 * 1024;

    public function transcribe(string $mediaId): ?string
    {
        $token = (string) config('services.whatsapp.access_token');
        $apiKey = (string) config('services.elevenlabs.api_key');
        if ($token === '' || $apiKey === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $mediaId) !== 1) {
            return null;
        }

        try {
            $meta = Http::withToken($token)->timeout(15)
                ->get(rtrim((string) config('services.whatsapp.base_url'), '/').'/'.$mediaId);
            $url = $meta->json('url');
            if (! $meta->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
                return null;
            }

            $audio = Http::withToken($token)->timeout(30)->get($url);
            $bytes = $audio->body();
            if (! $audio->successful() || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                return null;
            }

            $stt = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout(60)
                ->attach('file', $bytes, 'voice-note.ogg')
                ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                    'model_id' => (string) config('services.elevenlabs.stt_model', 'scribe_v1'),
                ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $text = trim((string) $stt->json('text', ''));

        return $stt->successful() && $text !== '' ? mb_substr($text, 0, 2000) : null;
    }
}
