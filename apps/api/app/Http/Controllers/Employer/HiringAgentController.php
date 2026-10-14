<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\EmployerAgentAction;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Support\Employers\Agent\HiringActionExecutor;
use App\Support\Employers\Agent\HiringAgent;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agentic hiring on the web: the hiring floor's Ask bar and the Taurus page
 * send what the recruiter typed or said here; a proposal comes back with a
 * Confirm button, and a spoken "yes" confirms it too. Milestone proposals
 * ("send L2 to the 3 who cleared L1?") are listed for the same buttons.
 *
 * Any member may ask and see proposals; only owners and recruiters can make
 * Taurus act (HiringActionExecutor checks again on every confirm).
 */
final class HiringAgentController extends Controller
{
    use ResolvesMembership;

    public function command(Request $request, EmployerWorkspace $workspace, HiringAgent $agent): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'job_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $job = null;
        if (($data['job_id'] ?? null) !== null) {
            $job = EmployerJob::query()->where('employer_workspace_id', $workspace->id)->find((int) $data['job_id']);
            abort_if($job === null, 404, 'That role is not in this workspace.');
        }

        $reply = $agent->respond($workspace, $request->user(), (string) $data['text'], EmployerAgentAction::CHANNEL_WEB, $job);

        return response()->json(['data' => $reply->toApi()]);
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
