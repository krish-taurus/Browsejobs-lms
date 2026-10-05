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

    /**
     * Workspace-wide applications across every job, for the Pipeline page's
     * List view (PRD-E pipeline kit). The per-job `index()` above can't serve
     * that view honestly: looping it over every job and flattening client-
     * side (the pre-redesign board did this) only ever sees the first 25
     * rows of *each* job and has no way to compute a workspace total.
     *
     * `job_id` and `search` scope both `data` and `counts`; `stage` scopes
     * only `data` — selecting a stage narrows the table without erasing the
     * other stages' totals in the nav (PRD-E: "does not erase counts for
     * other stages").
     */
    public function indexForWorkspace(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $base = EmployerJobApplication::query()
            ->whereHas('job', fn ($query) => $query->where('employer_workspace_id', $workspace->id));

        if ($request->filled('job_id')) {
            $base->where('employer_job_id', $request->integer('job_id'));
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $base->whereHas('candidate', fn ($query) => $query->where('name', 'like', "%{$term}%"));
        }

        $applications = (clone $base)
            ->with(['candidate', 'job', 'mockInterview'])
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->string('stage')->toString()))
            ->ranked()
            ->paginate(25)
            ->withQueryString();

        $stageRows = (clone $base)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        $byStage = [];
        foreach ($stageRows as $stage => $total) {
            $key = $stage instanceof EmployerApplicationStage ? $stage->value : (string) $stage;
            $byStage[$key] = (int) $total;
        }

        $counts = [
            'total' => (clone $base)->count(),
            'scored' => (clone $base)->whereNotNull('mock_score')->count(),
            'unscored' => (clone $base)->whereNull('mock_score')->count(),
            'hired' => (clone $base)->where('stage', EmployerApplicationStage::Hired->value)->count(),
            'by_stage' => $byStage,
        ];

        return EmployerApplicationResource::collection($applications)->additional(['counts' => $counts])->response();
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
