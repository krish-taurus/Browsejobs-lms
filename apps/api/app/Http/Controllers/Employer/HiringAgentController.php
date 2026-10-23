<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Support\Employers\Agent\AgentReply;
use App\Support\Employers\Agent\HiringActionExecutor;
use App\Support\Employers\Agent\HiringAgent;
use App\Support\Employers\Agent\ReplyLocalizer;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Agentic hiring on the web: the hiring floor's Ask bar and the Taurus page
 * send what the recruiter typed or said here; a proposal comes back with a
 * Confirm button, and a spoken "yes" confirms it too. Milestone proposals
 * ("send L2 to the 3 who cleared L1?") are listed for the same buttons.
 *
 * Any member may ask and see proposals; only owners and recruiters can make
 * Taurus act (HiringActionExecutor checks again on every confirm).
 *
 * transcribe() turns a recorded voice command into text for browsers that have
 * no speech recognition of their own (Firefox, Safari, some phones); Chrome
 * and Edge listen by themselves and never call it.
 */
final class HiringAgentController extends Controller
{
    use ResolvesMembership;

    public function command(Request $request, EmployerWorkspace $workspace, HiringAgent $agent, ReplyLocalizer $localizer): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'job_id' => ['sometimes', 'nullable', 'integer'],
            'lang' => ['sometimes', 'nullable', 'in:en,hi,kn'],
        ]);

        $job = null;
        if (($data['job_id'] ?? null) !== null) {
            $job = EmployerJob::query()->where('employer_workspace_id', $workspace->id)->find((int) $data['job_id']);
            abort_if($job === null, 404, 'That role is not in this workspace.');
        }

        $reply = $agent->respond($workspace, $request->user(), (string) $data['text'], EmployerAgentAction::CHANNEL_WEB, $job);

        // Answer in the language the recruiter used (Hindi, Kannada, English).
        $reply = new AgentReply($reply->intent, $localizer->localize($request->user(), (string) $data['text'], $reply->text, $data['lang'] ?? null, $workspace), $reply->action);

        return response()->json(['data' => $reply->toApi()]);
    }

    public function transcribe(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        $request->validate(['audio' => ['required', 'file', 'max:10240']]);

        $file = $request->file('audio');
        $mime = (string) $file->getMimeType();
        if (! str_starts_with($mime, 'audio/') && ! str_starts_with($mime, 'video/') && $mime !== 'application/ogg') {
            return response()->json(['error' => ['code' => 'not_audio', 'message' => 'That upload was not audio.']], 422);
        }

        // An empty or cut-short recording has nothing to transcribe (and would only fail upstream).
        if ((int) $file->getSize() < 300) {
            return response()->json(['error' => ['code' => 'too_short', 'message' => 'That recording was empty. Tap the mic and speak.']], 422);
        }

        $apiKey = (string) config('services.elevenlabs.api_key');
        if ($apiKey === '') {
            return $this->unavailable();
        }

        // No language is forced: the recruiter may speak English, Hindi or a mix.
        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(45)
            ->attach('file', (string) file_get_contents($file->getRealPath()), 'command.'.($file->guessExtension() ?: 'webm'))
            ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                'model_id' => (string) config('services.elevenlabs.stt_model', 'scribe_v1'),
                'tag_audio_events' => 'false',
            ]);

        if (! $response->successful()) {
            report(new \RuntimeException('ElevenLabs could not transcribe a voice command: '.mb_substr($response->body(), 0, 300)));

            return $this->unavailable();
        }

        return response()->json(['data' => ['text' => trim((string) $response->json('text', ''))]]);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'stt_unavailable', 'message' => 'Voice commands are not available just now. Type your request instead.']], 503);
    }

    public function index(Request $request, EmployerWorkspace $workspace, HiringAgent $agent): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        $jobId = $request->integer('job_id') ?: null;

        $open = EmployerAgentAction::query()->open()
            ->where('employer_workspace_id', $workspace->id)
            ->when($jobId !== null, fn ($q) => $q->where('employer_job_id', $jobId))
            ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('channel', EmployerAgentAction::CHANNEL_MILESTONE))
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return response()->json(['data' => [
            'can_act' => HiringActionExecutor::canAct($workspace, $request->user()),
            'open' => $open->map(fn (EmployerAgentAction $a) => $a->toApi())->values(),
        ]]);
    }

    public function confirm(Request $request, EmployerWorkspace $workspace, EmployerAgentAction $agentAction, HiringAgent $agent): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($agentAction->employer_workspace_id === $workspace->id, 404);

        // A refusal ("already done", "expired", "only a recruiter…") is Taurus's
        // answer, not a broken request: the console shows the reply either way.
        return response()->json(['data' => $agent->confirm($agentAction, $request->user())->toApi()]);
    }

    public function cancel(Request $request, EmployerWorkspace $workspace, EmployerAgentAction $agentAction, HiringAgent $agent): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($agentAction->employer_workspace_id === $workspace->id, 404);

        return response()->json(['data' => $agent->hold($agentAction, $request->user())->toApi()]);
    }
}
