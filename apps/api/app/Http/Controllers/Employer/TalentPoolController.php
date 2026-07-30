<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\InviteStudentToApply;
use App\Http\Controllers\Controller;
use App\Http\Resources\TalentPoolCandidateResource;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Employers\LmsTalentMatcher;
use App\Support\Employers\ResolvesMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trained BrowseJobs students matched to a JD (PRD-E F7) — the pool an
 * external board cannot offer, because these candidates already carry a
 * built CV, mastery record, and mock history.
 */
final class TalentPoolController extends Controller
{
    use ResolvesMembership;

    public function index(Request $request, EmployerWorkspace $workspace, EmployerJob $job, LmsTalentMatcher $matcher): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        return TalentPoolCandidateResource::collection($matcher->forJob($job))->response();
    }

    public function invite(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        User $candidate,
        InviteStudentToApply $invite,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $invite->handle($job, $candidate, $request->user());

        return response()->json(['ok' => true]);
    }
}
