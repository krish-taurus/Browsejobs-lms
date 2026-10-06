<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Turns a recorded clip into text for the hiring console's microphone.
 *
 * Chrome and Edge transcribe speech locally via the Web Speech API, which
 * Firefox and Safari have never implemented — clicking the console's mic on
 * either of those did nothing but recognise no browser support and fall back
 * to typing. This is the same conversation made to work everywhere: the
 * browser records with MediaRecorder (supported everywhere) and hands the
 * clip here, which proxies ElevenLabs' speech-to-text — the same vendor,
 * and the same server-side-only key, already used for the console's voice
 * on the way out (see SpeakController).
 *
 * With no key configured this answers 422 and the console tells the
 * employer to type instead, rather than hanging on a request that can never
 * succeed.
 */
final class TranscribeController extends Controller
{
    use ResolvesMembership;

    public function __invoke(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $validated = $request->validate([
            // A few seconds of compressed speech; comfortably covers a role
            // description without leaving room for someone to tunnel large
            // uploads through a microphone endpoint.
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $apiKey = (string) config('services.elevenlabs.api_key');

        if ($apiKey === '') {
            return response()->json([
                'message' => 'Voice input is not configured on this server. Please type instead.',
            ], 422);
        }

        /** @var UploadedFile $file */
        $file = $validated['audio'];

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(30)
            ->attach('file', $file->get(), $file->getClientOriginalName() ?: 'clip.webm')
            ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                'model_id' => 'scribe_v1',
            ]);

        if (! $response->successful()) {
            report(new \RuntimeException('ElevenLabs refused the console\'s recording: '.mb_substr($response->body(), 0, 300)));

            return response()->json([
                'message' => 'Could not transcribe that. Please try again or type instead.',
            ], 502);
        }

        $text = trim((string) $response->json('text', ''));

        return response()->json(['data' => ['text' => $text]]);
    }
}
