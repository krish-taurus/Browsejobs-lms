<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EntitlementFeature;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * "Take the mock for this job" (PRD-E F3) — the candidate side of an
 * internal posting.
 *
 * The mock IS the application: sitting it is how a candidate reaches an
 * employer, and the resulting score is what ranks them. It runs on the
 * existing mock engine via a hidden blueprint keyed to the JD, exactly as
 * the job-feed quick mock does, so answer/finish/scorecard/PRI are
 * unchanged and there is no second interviewer to keep in step.
 *
 * Retakes are unlimited while the candidate's mock package holds credits —
 * that is what the package buys. Each attempt costs one credit, and the
 * best attempt is the one the employer sees (see RecordApplicationGrade).
 * Applying itself stays free: the pack buys preparation and a better
 * score, never access to the employer.
 */
final readonly class StartEmployerJobMock
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(User $candidate, EmployerJob $job): MockInterview
    {
        return app(TenantContext::class)->run($candidate->tenant, function () use ($candidate, $job): MockInterview {
            $application = EmployerJobApplication::query()
                ->where('employer_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->first();

            if ($application === null) {
                throw ValidationException::withMessages([
                    'job' => 'Apply to this role before taking its mock.',
                ]);
            }

            // Resume an attempt already in flight rather than forking one and
            // silently charging a second credit.
            $existing = MockInterview::query()
                ->where('user_id', $candidate->id)
                ->where('status', MockInterview::STATUS_IN_PROGRESS)
                ->whereHas('blueprint', fn ($q) => $q->where('employer_job_id', $job->id))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Throws a ValidationException naming credits when the wallet is
            // empty; the controller turns that into a 402 with the offers.
            $this->entitlements->consumeCredits(
                $candidate,
                EntitlementFeature::VoiceMock->value,
                1,
                "employer_job_mock:{$job->id}",
            );

            $blueprint = $this->blueprintFor($job);

            $interview = MockInterview::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'user_id' => $candidate->id,
                'mock_blueprint_id' => $blueprint->id,
                'mode' => MockInterview::MODE_TEXT,
                'status' => MockInterview::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            MockTurn::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'mock_interview_id' => $interview->id,
                'role' => MockTurn::ROLE_INTERVIEWER,
                'body' => $blueprint->opening_question,
            ]);

            $application->increment('mock_attempts');

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
