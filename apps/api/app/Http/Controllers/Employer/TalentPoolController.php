<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Actions\Employers\InviteStudentToApply;
use App\Actions\Employers\ShortlistTalentPoolCandidate;
use App\Http\Controllers\Controller;
use App\Http\Resources\TalentPoolCandidateResource;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\Employers\LmsTalentMatcher;
use App\Support\Employers\ResolvesMembership;
use App\Support\Employers\SampleTalentMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Trained BrowseJobs students matched to a JD (PRD-E F7) — the pool an
 * external board cannot offer, because these candidates already carry a
 * built CV, mastery record, and mock history.
 */
final class TalentPoolController extends Controller
{
    use ResolvesMembership;

    public function index(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        LmsTalentMatcher $matcher,
        SampleTalentMatcher $sampleMatcher,
    ): JsonResponse {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $candidates = $matcher->forJob($job);

        // Opt-in only, per request — never persisted, never on by default.
        // Demoing the product before real candidate volume exists shouldn't
        // require ever writing a fake row into real candidate data.
        if ($request->boolean('sample')) {
            $candidates = $candidates->concat($sampleMatcher->forJob($job))->sortByDesc('match_score')->values();
        }

        return TalentPoolCandidateResource::collection($candidates)->response();
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

    /**
     * One click from the Talent Pool: mark shortlisted and notify the
     * candidate over WhatsApp — see ShortlistTalentPoolCandidate. Distinct
     * from invite(): this doesn't ask them to apply first, it tells them
     * they've already been picked.
     */
    public function shortlist(
        Request $request,
        EmployerWorkspace $workspace,
        EmployerJob $job,
        User $candidate,
        ShortlistTalentPoolCandidate $shortlist,
    ): JsonResponse {
        $member = $this->membershipOrFail($workspace, $request->user());
        abort_unless($member->role->managesPipeline(), 403);
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $shortlist->handle($job, $candidate, $request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * A candidate's own CV facts, for an employer browsing the Talent Pool
     * before they've applied anywhere (Aug 2026 employer request) — the same
     * plain CvProfile read CandidateProfile::cv() already does for an
     * applied candidate's profile page, just reachable without an
     * application existing yet.
     */
    public function cv(Request $request, EmployerWorkspace $workspace, EmployerJob $job, User $candidate): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $profile = CvProfile::query()->where('user_id', $candidate->id)->first();
        if ($profile === null) {
            return response()->json(['error' => ['message' => 'No CV on file.']], 404);
        }

        $data = is_array($profile->data) ? $profile->data : [];

        return response()->json(['data' => [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : null,
            'skills' => array_values(array_filter((array) ($data['skills'] ?? []), 'is_string')),
            'experience' => array_values(array_filter((array) ($data['experience'] ?? []), 'is_array')),
            'education' => array_values(array_filter((array) ($data['education'] ?? []), 'is_array')),
        ]]);
    }

    /**
     * A signed URL to the candidate's AI Readiness Interview recording —
     * their best-scoring completed attempt, same selection CvMockController
     * uses for the candidate's own view of it. Every Talent Pool candidate
     * has completed this interview (it's the pool's own eligibility gate —
     * see LmsTalentMatcher), so this exists for anyone who legitimately
     * appears here; 404 only if the upload itself never landed.
     */
    public function recording(Request $request, EmployerWorkspace $workspace, EmployerJob $job, User $candidate): JsonResponse
    {
        $this->membershipOrFail($workspace, $request->user());
        abort_unless($job->employer_workspace_id === $workspace->id, 404);

        $best = MockInterview::query()
            ->where('user_id', $candidate->id)
            ->whereHas('blueprint', fn ($q) => $q->where('user_id', $candidate->id))
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->orderByDesc('overall_score')
            ->first();

        if ($best === null || $best->recording_url === null) {
            return response()->json(['error' => ['message' => 'No recording available.']], 404);
        }

        $url = str_starts_with($best->recording_url, 'http')
            ? $best->recording_url
            : $this->signedUrl($best->recording_url);

        if ($url === null) {
            return response()->json(['error' => ['message' => 'Could not open the recording.']], 404);
        }

        return response()->json(['data' => ['url' => $url]]);
    }

    private function signedUrl(string $path): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return null;
        }
    }
}
