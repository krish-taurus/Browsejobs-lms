<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\SaveInterviewProcess;
use App\Actions\Employers\SendInterviewRound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\SaveInterviewProcessRequest;
use App\Http\Resources\EmployerInterviewResource;
use App\Http\Resources\EmployerJobRoundResource;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerJobRound;
use App\Models\EmployerWorkspace;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\ResolvesMembership;
use App\Support\Roles\RoleTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The interview process an employer designs for a JD, and sending one of its
 * rounds to a candidate (PRD-E F18).
 */
final class InterviewProcessController extends Controller
{
    use ResolvesMembership;

    public function index(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        InterviewProcess $process,
        RoleTaxonomy $taxonomy,
    ): JsonResponse {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $suggested = $taxonomy->skillsForTitle($job->title);

        return response()->json([
            'data' => EmployerJobRoundResource::collection($process->forJob($job))->resolve(),
            'meta' => [
                'kinds' => EmployerJobRound::KINDS,
                'competencies' => $taxonomy->competencies(),
                'question_formats' => $taxonomy->questionFormats(),
                'selectable_skills' => array_values(array_unique([
                    ...array_map(mb_strtolower(...), $job->skills ?? []),
                    ...$suggested['core'],
                    ...$suggested['optional'],
                ])),
                'default_window_hours' => (int) config('employers.interview_window_hours'),
                'has_mock' => $job->currentMock() !== null,
            ],
        ]);
    }

    public function update(
        SaveInterviewProcessRequest $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        SaveInterviewProcess $action,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        /** @var list<array<string, mixed>> $rounds */
        $rounds = $request->validated('rounds');
        $saved = $action->handle($job, $rounds, $request->user());

        return response()->json([
            'data' => EmployerJobRoundResource::collection(collect($saved))->resolve(),
        ]);
    }

    /**
     * Send one round to one candidate by hand. The same action the automatic
     * dispatcher uses, so a hand-sent round and a threshold-sent one are the
     * same thing in the record.
     */
    public function send(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        EmployerJobApplication $application,
        EmployerJobRound $round,
        SendInterviewRound $action,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($application->employer_job_id === $job->id, 404);
        abort_unless($round->employer_job_id === $job->id, 404);

        $interview = $action->handle($application, $round, $request->user());

        return response()->json(['data' => (new EmployerInterviewResource($interview))->resolve()], 201);
    }
}
