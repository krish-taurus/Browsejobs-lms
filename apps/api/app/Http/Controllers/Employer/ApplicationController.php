<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\MoveApplicationStage;
use App\Enums\EmployerApplicationStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\MoveApplicationStageRequest;
use App\Http\Resources\EmployerApplicationResource;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApplicationController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $applications = $job->hasMany(EmployerJobApplication::class, 'employer_job_id')->getQuery()
            ->with(['candidate', 'mockInterview'])
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->string('stage')->toString()))
            ->when($request->boolean('graded_only'), fn ($query) => $query->whereNotNull('graded_at'))
            ->ranked()
            ->paginate(25);

        return EmployerApplicationResource::collection($applications)->response();
    }

    public function show(Request $request, EmployerWorkspace $workspace, EmployerJob $job, EmployerJobApplication $application): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($application->employer_job_id === $job->id, 404);

        return (new EmployerApplicationResource(
            $application->load(['candidate', 'mockInterview', 'transitions']),
        ))->response();
    }

    public function moveStage(
        MoveApplicationStageRequest $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        EmployerJobApplication $application,
        MoveApplicationStage $move,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);
        abort_unless($application->employer_job_id === $job->id, 404);

        $updated = $move->handle(
            $application,
            EmployerApplicationStage::from($request->string('stage')->toString()),
            $request->user(),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return (new EmployerApplicationResource($updated->load(['candidate', 'mockInterview'])))->response();
    }
}
