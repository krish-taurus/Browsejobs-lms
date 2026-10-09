<?php

declare(strict_types=1);

namespace App\Support\Voice;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Text-to-speech through ElevenLabs, shared by the employer hiring console and
 * the Taurus command centre. The key stays on the server; callers get MP3
 * bytes or null.
 *
 * Null means "use the browser voice": no key or voice configured, or
 * ElevenLabs refused — a TTS outage must never break the page that asked.
 * Clips are cached by sha256(voice|model|text) because consoles repeat the
 * same handful of lines and paying for each again is waste.
 */
final class ElevenLabsSpeaker
{
    private const DISK = 'local';

    private const FOLDER = 'employer-speech';

    /** Clips older than this are swept on the way past. */
    private const KEEP_DAYS = 7;

    /**
     * MP3 bytes for the text, or null when the caller should fall back.
     *
     * `$voice` speaks with someone else's account (a Taurus workspace's own
     * ElevenLabs key); without it the platform's services.elevenlabs is used.
     *
     * @param  array{api_key: string, voice_id: string, model?: string}|null  $voice
     */
    public function speak(string $text, ?array $voice = null): ?string
    {
        $apiKey = (string) ($voice['api_key'] ?? config('services.elevenlabs.api_key'));
        $voiceId = (string) ($voice['voice_id'] ?? config('services.elevenlabs.voice_id'));

        if ($apiKey === '' || $voiceId === '') {
            return null;
        }

        $model = (string) ($voice['model'] ?? config('services.elevenlabs.model', 'eleven_turbo_v2_5'));
        $text = trim($text);

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

            if (! $response->successful()) {
                report(new RuntimeException('ElevenLabs refused the console line: '.mb_substr($response->body(), 0, 300)));

                return null;
            }

            $disk->put($path, $response->body());
            $this->pruneOldClips($disk);
        }

        return $disk->get($path);
    }

    /** Keep the cache from growing forever; it is a convenience, not a record. */
    private function pruneOldClips(Filesystem $disk): void
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();

        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
