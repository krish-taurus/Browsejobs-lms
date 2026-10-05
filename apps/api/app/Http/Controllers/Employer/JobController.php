<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\ChangeEmployerJobStatus;
use App\Actions\Employers\CreateEmployerJob;
use App\Actions\Employers\PublishEmployerJob;
use App\Enums\EmployerJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\ChangeJobStatusRequest;
use App\Http\Requests\Employers\StoreJobRequest;
use App\Http\Requests\Employers\UpdateJobRequest;
use App\Http\Resources\EmployerJobResource;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JobController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());

        $jobs = $workspace->jobs()
            ->with('mocks')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")
                        // Case-insensitive substring match against the skills
                        // JSON array — JSON_SEARCH's third argument accepts
                        // SQL LIKE wildcards.
                        ->orWhereRaw('JSON_SEARCH(LOWER(skills), "one", ?) IS NOT NULL', ['%'.mb_strtolower($term).'%']);
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Workspace-wide, independent of the status/search filters above — the
        // Jobs page's summary strip and filter-pill counts must never quietly
        // narrow to whatever the current search happens to match (PRD-E jobs
        // kit: "these metrics represent the workspace, independent of the
        // local search and status selection").
        $counts = [
            'total' => $workspace->jobs()->count(),
            'published' => $workspace->jobs()->where('status', EmployerJobStatus::Published->value)->count(),
            'closed' => $workspace->jobs()->where('status', EmployerJobStatus::Closed->value)->count(),
            'open_positions' => (int) $workspace->jobs()->where('status', EmployerJobStatus::Published->value)->sum('openings'),
        ];

        return EmployerJobResource::collection($jobs)->additional(['counts' => $counts])->response();
    }

    public function store(StoreJobRequest $request, EmployerWorkspace $workspace, CreateEmployerJob $create): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403, 'Hiring managers cannot create JDs.');

        $job = $create->handle($workspace, $request->user(), $request->validated());

        return (new EmployerJobResource($job))->response()->setStatusCode(201);
    }

    public function show(Request $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        return (new EmployerJobResource($job->load('mocks')))->response();
    }

    public function update(UpdateJobRequest $request, EmployerWorkspace $workspace, EmployerJob $job): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $job->update($request->validated());

        return (new EmployerJobResource($job->fresh()->load('mocks')))->response();
    }

    public function publish(Request $request, EmployerWorkspace $workspace, EmployerJob $job, PublishEmployerJob $publish): JsonResponse
    {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $published = $publish->handle($job, $request->user());

        return (new EmployerJobResource($published->load('mocks')))->response();
    }

    public function changeStatus(
        ChangeJobStatusRequest $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        ChangeEmployerJobStatus $changeStatus,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $updated = $changeStatus->handle(
            $job,
            EmployerJobStatus::from($request->string('status')->toString()),
            $request->user(),
        );

        return (new EmployerJobResource($updated->load('mocks')))->response();
    }
}
