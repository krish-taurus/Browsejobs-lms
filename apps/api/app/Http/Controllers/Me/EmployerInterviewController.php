<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\EmployerInterviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employers\SubmitInterviewRequest;
use App\Http\Resources\CandidateInterviewResource;
use App\Jobs\GradeEmployerInterview;
use App\Models\EmployerInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Candidate side of async L1/L2 rounds (PRD-E F5): list invites, see
 * questions, submit answers once. Grading is queued; the candidate sees
 * "submitted" and the employer gets the graded report when ready.
 */
final class EmployerInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $interviews = EmployerInterview::query()
            ->whereHas('application', fn ($query) => $query->where('candidate_id', $request->user()->id))
            ->with('application.job')
            ->latest('invited_at')
            ->paginate(20);

        return CandidateInterviewResource::collection($interviews)->response();
    }

    public function show(Request $request, EmployerInterview $interview): JsonResponse
    {
        $this->candidateOrFail($interview, $request);

        return (new CandidateInterviewResource($interview->load('application.job')))->response();
    }

    public function start(Request $request, EmployerInterview $interview): JsonResponse
    {
        $this->candidateOrFail($interview, $request);

        if ($interview->status !== EmployerInterviewStatus::Invited) {
            throw ValidationException::withMessages(['interview' => 'This round has already been started or completed.']);
        }
        if ($interview->expires_at->isPast()) {
            $interview->update(['status' => EmployerInterviewStatus::Expired->value]);
            throw ValidationException::withMessages(['interview' => 'This round has expired. Ask the employer to re-invite you.']);
        }

        $interview->update([
            'status' => EmployerInterviewStatus::InProgress->value,
            'started_at' => now(),
        ]);

        return (new CandidateInterviewResource($interview->fresh()->load('application.job')))->response();
    }

    public function submit(SubmitInterviewRequest $request, EmployerInterview $interview): JsonResponse
    {
        $this->candidateOrFail($interview, $request);

        if (! in_array($interview->status, [EmployerInterviewStatus::InProgress, EmployerInterviewStatus::Invited], true)) {
            throw ValidationException::withMessages(['interview' => 'This round has already been submitted.']);
        }
        if ($interview->expires_at->isPast()) {
            $interview->update(['status' => EmployerInterviewStatus::Expired->value]);
            throw ValidationException::withMessages(['interview' => 'This round has expired.']);
        }

        $interview->update([
            'status' => EmployerInterviewStatus::Submitted->value,
            'answers' => $request->validated()['answers'],
            'started_at' => $interview->started_at ?? now(),
            'submitted_at' => now(),
        ]);

        GradeEmployerInterview::dispatch($interview->id);

        return (new CandidateInterviewResource($interview->fresh()->load('application.job')))->response();
    }

    private function candidateOrFail(EmployerInterview $interview, Request $request): void
    {
        $application = $interview->application;
        abort_if($application === null || $application->candidate_id !== $request->user()->id, 404);
    }
}
