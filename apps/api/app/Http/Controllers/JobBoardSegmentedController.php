<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EmployerJobStatus;
use App\Http\Resources\PublicEmployerJobResource;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Support\JobBoard\JobBoardQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The segmented job board (PRD-E F10) — served both to signed-out visitors
 * (tenant resolved by domain) and to signed-in candidates, who additionally
 * get their own application state marked on internal postings.
 */
final class JobBoardSegmentedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $term = $request->filled('q') ? $request->string('q')->trim()->toString() : null;

        $run = fn (): array => (new JobBoardQuery($viewer))->handle($term);

        $board = $viewer !== null
            ? app(TenantContext::class)->run($viewer->tenant, $run)
            : $run();

        return response()->json(['data' => $board]);
    }

    /**
     * One published role, readable signed out.
     *
     * A job seeker should be able to read the description before deciding
     * whether to hand over an email address, let alone sit an interview.
     * Gating the description behind registration asks someone to commit to a
     * role they have not been allowed to read.
     *
     * Reuses PublicEmployerJobResource, so the employer's disclosure choices
     * (CTC only when they opted in) hold here exactly as they do inside the
     * portal — a public route must not become the looser one.
     */
    public function show(Request $request, EmployerJob $job): JsonResponse
    {
        abort_unless($job->status === EmployerJobStatus::Published, 404);

        $viewer = $request->user();

        return (new PublicEmployerJobResource($job->load('workspace')))
            ->additional(['meta' => [
                'mock_ready' => $job->currentMock()?->status->value === 'ready',
                // Lets the page offer "continue" instead of "apply" rather
                // than failing on submit for someone already in the pipeline.
                'has_applied' => $viewer !== null && EmployerJobApplication::query()
                    ->where('employer_job_id', $job->id)
                    ->where('candidate_id', $viewer->id)
                    ->exists(),
            ]])
            ->response();
    }
}
