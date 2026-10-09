<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\AskHiringFloor;
use App\Actions\Employers\BuildHiringFloor;
use App\Actions\Taurus\BrainNotConfigured;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\HiringFloorAskRequest;
use App\Http\Requests\Employers\HiringFloorRequest;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Services\AI\AiBudgetExceeded;
use App\Support\AI\ProviderNotConfigured;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

/**
 * The hiring floor (ADR 0052): the employer's pipeline drawn as the Taurus
 * recruitment floor — stage stations, the bots working each stage, and a
 * feed of moves — plus "ask Taurus" about it. Members only; a job filter must
 * belong to this workspace.
 */
final class HiringFloorController extends Controller
{
    use ResolvesMembership;

    private const NOT_CONNECTED = "Taurus isn't connected yet. Please try again later.";

    public function show(HiringFloorRequest $request, EmployerWorkspace $workspace, BuildHiringFloor $floor): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $job = $this->jobOrFail($workspace, $request->validated('job_id'));

        return response()->json(['data' => $floor->handle($workspace, $job)]);
    }

    public function ask(HiringFloorAskRequest $request, EmployerWorkspace $workspace, AskHiringFloor $ask): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $job = $this->jobOrFail($workspace, $request->validated('job_id'));

        try {
            $answer = $ask->handle($request->user(), $workspace, $job, (string) $request->validated('question'));
        } catch (BrainNotConfigured|ProviderNotConfigured) {
            return $this->error('brain_not_configured', self::NOT_CONNECTED, 503);
        } catch (AiBudgetExceeded) {
            return $this->error('ai_budget_exceeded', 'Taurus has answered a lot today. Please try again tomorrow.', 429);
        } catch (RequestException|ConnectionException) {
            return $this->error('brain_failed', 'Taurus could not answer just now. Please try again.', 502);
        }

        return response()->json(['data' => $answer]);
    }

    private function jobOrFail(EmployerWorkspace $workspace, mixed $jobId): ?EmployerJob
    {
        if ($jobId === null) {
            return null;
        }

        $job = EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->whereKey((int) $jobId)
            ->first(['id', 'title', 'status']);

        abort_if($job === null, 404, 'That role is not in this workspace.');

        return $job;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
