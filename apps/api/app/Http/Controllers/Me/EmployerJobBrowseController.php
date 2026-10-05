<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Employers\ApplyToEmployerJob;
use App\Actions\Employers\StartEmployerJobMock;
use App\Enums\EmployerJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\ApplyToJobRequest;
use App\Http\Resources\CandidateApplicationResource;
use App\Http\Resources\PublicEmployerJobResource;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Candidate-side surface for employer JDs (PRD-E F3): browse published
 * roles, apply free, track own applications. Tenant scoping comes from
 * the authenticated user.
 */
final class EmployerJobBrowseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = EmployerJob::query()
            ->where('status', EmployerJobStatus::Published->value)
            ->with('workspace')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('role_family', 'like', $term));
            })
            ->orderByDesc('published_at')
            ->paginate(20);

        return PublicEmployerJobResource::collection($jobs)->response();
    }

    public function show(Request $request, EmployerJob $job): JsonResponse
    {
        abort_unless($job->status === EmployerJobStatus::Published, 404);

        return (new PublicEmployerJobResource($job->load('workspace')))->response();
    }

    public function apply(ApplyToJobRequest $request, EmployerJob $job, ApplyToEmployerJob $apply): JsonResponse
    {
        $application = $apply->handle(
            $job,
            $request->user(),
            $request->validated()['knockout_answers'] ?? null,
        );

        return (new CandidateApplicationResource($application->load('job')))->response()->setStatusCode(201);
    }

    /**
     * Start (or resume) this JD's mock. Capped at a flat N attempts per job
     * (CRM-editable) — a plain validation error past the cap, not a 402: no
     * wallet or purchase is involved in this flow at all (see
     * StartEmployerJobMock), so there is nothing to offer, just a hard stop.
     */
    public function mock(Request $request, EmployerJob $job, StartEmployerJobMock $start): JsonResponse
    {
        abort_unless($job->status === EmployerJobStatus::Published, 404);

        $interview = $start->handle($request->user(), $job);

        return response()->json(['data' => ['mock_id' => $interview->id]], 201);
    }

    /**
     * Where the candidate stands against this JD's own mock — the signal
     * the apply page uses to decide whether Apply is even offered (PRD-E
     * F3): nothing started, an attempt to resume, or a completed one with
     * its score. A completed attempt always wins over a stale in-progress
     * row from an earlier retake. Also carries the per-job attempt cap
     * (CRM-editable) so the apply page can show it before the candidate
     * ever hits the hard stop in StartEmployerJobMock.
     */
    public function myMock(Request $request, EmployerJob $job, EntitlementService $entitlements): JsonResponse
    {
        $all = MockInterview::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('blueprint', fn ($q) => $q->where('employer_job_id', $job->id))
            ->get();

        $attempts = [
            'used' => $all->count(),
            'limit' => $entitlements->settings()->employer_mock_attempts_per_job,
        ];

        $completed = $all->where('status', MockInterview::STATUS_COMPLETED)->sortByDesc('overall_score')->first();
        if ($completed !== null) {
            return response()->json(['data' => ['status' => 'completed', 'mock_id' => $completed->id, 'score' => $completed->overall_score, 'attempts' => $attempts]]);
        }

        $inProgress = $all->firstWhere('status', MockInterview::STATUS_IN_PROGRESS);
        if ($inProgress !== null) {
            return response()->json(['data' => ['status' => 'in_progress', 'mock_id' => $inProgress->id, 'score' => null, 'attempts' => $attempts]]);
        }

        return response()->json(['data' => ['status' => 'none', 'mock_id' => null, 'score' => null, 'attempts' => $attempts]]);
    }

    public function applications(Request $request): JsonResponse
    {
        $applications = EmployerJobApplication::query()
            ->where('candidate_id', $request->user()->id)
            ->with('job')
            ->latest()
            ->paginate(20);

        return CandidateApplicationResource::collection($applications)->response();
    }
}
