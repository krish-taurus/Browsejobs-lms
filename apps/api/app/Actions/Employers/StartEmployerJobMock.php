<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Actions\Mocks\StartMockInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Entitlements\ActiveCareerBoost;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * "Take the mock for this job" (PRD-E F3) — the candidate side of an
 * internal posting.
 *
 * Taken BEFORE applying: a completed attempt is now a precondition of
 * Apply (see ApplyToEmployerJob), not a follow-up to it. Runs as a normal
 * text-mode mock — the existing "interview room" page
 * ((portal)/mock/[id]/room) wraps any text-mode session in a spoken-call
 * UI on top: ElevenLabs speaks each question, the mic transcribes spoken
 * answers, no Vapi or any other live-call provider required. Switch to
 * MODE_VOICE (see git history) once a real Vapi key is configured — the
 * gating and scoring logic here does not care which mode produced the
 * completed interview.
 *
 * Capped at a flat N attempts per job (CRM-editable), hard stop past the
 * cap — deliberately its own counter, separate from the course-mock room
 * cap in {@see StartMockInterview} and from the
 * voice_mock credit wallet: taking this job's interview never touches a
 * shared pool, so it can never be confused with, or starved by, unrelated
 * course-mock usage. Past the free cap, a purchased Career Boost's bonus
 * pool is checked next (Aug 2026) — spent across whichever jobs need it,
 * not restricted to this one.
 */
final readonly class StartEmployerJobMock
{
    public function __construct(
        private EntitlementService $entitlements,
        private ActiveCareerBoost $boost,
    ) {}

    public function handle(User $candidate, EmployerJob $job): MockInterview
    {
        return app(TenantContext::class)->run($candidate->tenant, function () use ($candidate, $job): MockInterview {
            $attempts = MockInterview::query()
                ->where('user_id', $candidate->id)
                ->whereHas('blueprint', fn ($q) => $q->where('employer_job_id', $job->id))
                ->latest('id')
                ->get();

            // Resume an attempt already in flight rather than forking one and
            // silently counting a second attempt against the cap.
            $existing = $attempts->firstWhere('status', MockInterview::STATUS_IN_PROGRESS);
            if ($existing !== null) {
                return $existing;
            }

            $limit = $this->entitlements->settings()->employer_mock_attempts_per_job;
            if ($attempts->count() >= $limit && ! $this->boost->consumeMock($candidate)) {
                throw ValidationException::withMessages([
                    'attempts' => "You've used all {$limit} interview attempts for this role. A Career Boost pack in the Store adds more.",
                ]);
            }

            $blueprint = $this->blueprintFor($job);

            $interview = MockInterview::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'user_id' => $candidate->id,
                'mock_blueprint_id' => $blueprint->id,
                'mode' => MockInterview::MODE_TEXT,
                'is_room' => true,
                'status' => MockInterview::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            MockTurn::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'mock_interview_id' => $interview->id,
                'role' => MockTurn::ROLE_INTERVIEWER,
                'body' => $blueprint->opening_question,
            ]);

            // The application may not exist yet (mock now comes first) — only
            // an already-existing one (a retake after applying) gets its
            // attempt count bumped live. ApplyToEmployerJob backfills the
            // count from actual MockInterview rows when it creates a fresh one.
            EmployerJobApplication::query()
                ->where('employer_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->increment('mock_attempts');

            return $interview;
        });
    }

    /**
     * One hidden blueprint per JD. Competencies come from the JD's own skill
     * list so the interviewer stays on this role, and the opening question
     * prefers the generated JD mock's first question when one exists — that
     * question set is what the employer's rubric was written against.
     */
    private function blueprintFor(EmployerJob $job): MockBlueprint
    {
        $role = mb_substr($job->title, 0, 255);
        $company = $job->workspace?->name ?? 'the hiring team';

        $firstQuestion = null;
        $questions = $job->currentMock()?->questions;
        if (is_array($questions) && isset($questions[0])) {
            $first = $questions[0];
            $firstQuestion = is_array($first) ? ($first['question'] ?? null) : (is_string($first) ? $first : null);
        }

        return MockBlueprint::query()->firstOrCreate(
            ['tenant_id' => $job->tenant_id, 'employer_job_id' => $job->id],
            [
                'role_title' => $role,
                'skill' => null,
                'competencies' => array_slice($job->skills ?? [], 0, 6) ?: ['role fit', 'communication'],
                'opening_question' => mb_substr(
                    is_string($firstQuestion) && $firstQuestion !== ''
                        ? $firstQuestion
                        : "You're interviewing for {$role} at {$company}. Introduce yourself, then tell me why you're a fit for this specific role.",
                    0,
                    500,
                ),
                'is_active' => false, // Hidden from course blueprint pickers.
            ],
        );
    }
}
