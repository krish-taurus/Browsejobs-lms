<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use App\Support\Voice\ElevenLabsSpeaker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function __invoke(Request $request, EmployerWorkspace $workspace, ElevenLabsSpeaker $speaker): SymfonyResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:600'],
        ]);

        // Null = no key/voice, or ElevenLabs refused: the console falls back to
        // the browser voice. A TTS outage must not stop somebody posting a job.
        $audio = $speaker->speak($validated['text']);

        if ($audio === null) {
            return response()->noContent();
        }

        return response($audio, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
