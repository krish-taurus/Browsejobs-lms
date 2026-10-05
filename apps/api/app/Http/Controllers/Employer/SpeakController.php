<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gives the hiring console a real voice.
 *
 * Neural Ops answers out loud while an employer dictates a role, and the
 * browser's own synthesiser hands them whatever robot their laptop ships with.
 * This proxies ElevenLabs — the same voice the mock interviewer already uses —
 * so the console sounds the same on every machine.
 *
 * The key never reaches the browser, which is the whole reason this endpoint
 * exists rather than the page calling ElevenLabs directly.
 *
 * With no key configured it answers 204 and the console falls back to the
 * browser voice, so it is never left mute.
 */
final class SpeakController extends Controller
{
    use ResolvesMembership;

    private const DISK = 'local';

    private const FOLDER = 'employer-speech';

    /** Clips older than this are swept on the way past. */
    private const KEEP_DAYS = 7;

    public function __invoke(Request $request, EmployerWorkspace $workspace): SymfonyResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:600'],
        ]);

        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = (string) config('services.elevenlabs.voice_id');

        if ($apiKey === '' || $voiceId === '') {
            return response()->noContent();
        }

        $model = (string) config('services.elevenlabs.model', 'eleven_turbo_v2_5');
        $text = trim($validated['text']);

        // The console repeats itself by design — the same handful of lines, over
        // and over, for every role posted. Paying for each one again is waste.
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

            // A TTS outage must not stop somebody posting a job: anything but a
            // 200 sends the console back to the browser voice.
            if (! $response->successful()) {
                report(new \RuntimeException('ElevenLabs refused the console line: '.mb_substr($response->body(), 0, 300)));

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
