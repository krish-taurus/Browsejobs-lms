<?php

declare(strict_types=1);

namespace App\Http\Controllers\Taurus;

use App\Actions\Taurus\AskTaurus;
use App\Actions\Taurus\BrainNotConfigured;
use App\Actions\Taurus\BuildFloorState;
use App\Actions\Taurus\DecideTaurusTask;
use App\Actions\Taurus\TaskNotAwaitingApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taurus\AskTaurusRequest;
use App\Http\Requests\Taurus\DecideTaskRequest;
use App\Http\Requests\Taurus\FloorStateRequest;
use App\Http\Requests\Taurus\SpeakRequest;
use App\Models\TaurusTask;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceMember;
use App\Services\AI\AiBudgetExceeded;
use App\Support\AI\ProviderNotConfigured;
use App\Support\Taurus\WorkspaceBrain;
use App\Support\Taurus\WorkspacePresenter;
use App\Support\Voice\ElevenLabsSpeaker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A Taurus workspace's console (ADR 0052): the floor, approvals, "Ask Taurus"
 * and its voice. Membership, suspension and role are enforced by the
 * `taurus.member` middleware; every query here is scoped to the one workspace
 * in the URL.
 */
final class WorkspaceController extends Controller
{
    /** The caller's workspaces (any status — the switcher shows suspended ones as such). */
    public function index(Request $request): JsonResponse
    {
        $rows = TaurusWorkspaceMember::query()
            ->where('user_id', $request->user()->id)
            ->with('workspace')
            ->get()
            ->filter(fn (TaurusWorkspaceMember $m): bool => $m->workspace !== null)
            ->sortBy(fn (TaurusWorkspaceMember $m): string => ($m->workspace->is_owner ? '0' : '1').mb_strtolower($m->workspace->name))
            ->map(fn (TaurusWorkspaceMember $m): array => WorkspacePresenter::forMember($m->workspace, $m->role->value))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function state(FloorStateRequest $request, TaurusWorkspace $workspace, BuildFloorState $state): JsonResponse
    {
        return response()->json(['data' => $state->handle($workspace, $request->floor())]);
    }

    public function approve(DecideTaskRequest $request, TaurusWorkspace $workspace, TaurusTask $task, DecideTaurusTask $decide): JsonResponse
    {
        return $this->decide($request, $task, $decide, true);
    }

    public function reject(DecideTaskRequest $request, TaurusWorkspace $workspace, TaurusTask $task, DecideTaurusTask $decide): JsonResponse
    {
        return $this->decide($request, $task, $decide, false);
    }

    public function ask(AskTaurusRequest $request, TaurusWorkspace $workspace, AskTaurus $ask): JsonResponse
    {
        try {
            $answer = $ask->handle(
                $request->user(),
                $workspace,
                (string) $request->validated('question'),
                (string) $request->validated('floor', 'ops'),
            );
        } catch (BrainNotConfigured|ProviderNotConfigured) {
            return $this->error('brain_not_configured', 'Add an LLM key in Taurus → Brain & voice.', 503);
        } catch (AiBudgetExceeded) {
            return $this->error('ai_budget_exceeded', 'Your daily AI budget is used up. Try again tomorrow.', 429);
        } catch (RequestException $e) {
            return $this->error('brain_failed', "The brain could not answer (HTTP {$e->response->status()}).", 502);
        } catch (ConnectionException) {
            return $this->error('brain_failed', 'The brain could not be reached.', 502);
        }

        return response()->json(['data' => $answer]);
    }

    public function speak(SpeakRequest $request, TaurusWorkspace $workspace, WorkspaceBrain $brain, ElevenLabsSpeaker $speaker): SymfonyResponse
    {
        // A client workspace speaks only with its own ElevenLabs key; without
        // one it gets 204 and the console uses the browser voice.
        $voice = $brain->voice($workspace);
        $audio = $voice !== null ? $speaker->speak((string) $request->validated('text'), $voice) : null;

        if ($audio === null) {
            return response()->noContent();
        }

        return response($audio, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function members(TaurusWorkspace $workspace): JsonResponse
    {
        $rows = $workspace->members()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn (TaurusWorkspaceMember $m): array => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role->value,
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function decide(DecideTaskRequest $request, TaurusTask $task, DecideTaurusTask $decide, bool $approve): JsonResponse
    {
        try {
            $task = $decide->handle($task, $approve, $request->validated('note'), $request->user());
        } catch (TaskNotAwaitingApproval $e) {
            return $this->error('not_awaiting_approval', $e->getMessage(), 409);
        }

        return response()->json(['data' => BuildFloorState::task($task, $task->agent?->name)]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
