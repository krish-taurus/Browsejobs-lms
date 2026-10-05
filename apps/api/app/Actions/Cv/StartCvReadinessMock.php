<?php

declare(strict_types=1);

namespace App\Actions\Cv;

use App\Models\CvProfile;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * "Take the AI Readiness Interview" (candidate request, Aug 2026) — a
 * general, CV-driven mock any student can take, not tied to a specific job.
 * Modelled directly on StartEmployerJobMock: same resume-in-progress
 * behaviour, same "runs as a normal text-mode mock, the room page wraps it
 * in the spoken-call UI" reuse, same one-attempt-cap-per-kind isolation —
 * cv_mock_attempts_limit is its own counter, untouched by course-mock or
 * employer-JD-mock usage and vice versa.
 *
 * Completing this (see FinishMockInterview) is what makes a student's
 * profile discoverable in an employer's Talent Pool search even without
 * applying to any job — the interview IS the opt-in.
 */
final readonly class StartCvReadinessMock
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(User $candidate): MockInterview
    {
        return app(TenantContext::class)->run($candidate->tenant, function () use ($candidate): MockInterview {
            if (! CvProfile::query()->where('user_id', $candidate->id)->exists()) {
                throw ValidationException::withMessages([
                    'cv' => 'Build your CV before taking the AI Readiness Interview — go to My CV.',
                ]);
            }

            $attempts = MockInterview::query()
                ->where('user_id', $candidate->id)
                ->whereHas('blueprint', fn ($q) => $q->where('user_id', $candidate->id))
                ->latest('id')
                ->get();

            // Resume an attempt already in flight rather than forking one and
            // silently counting a second attempt against the cap.
            $existing = $attempts->firstWhere('status', MockInterview::STATUS_IN_PROGRESS);
            if ($existing !== null) {
                return $existing;
            }

            $limit = $this->entitlements->settings()->cv_mock_attempts_limit;
            if ($attempts->count() >= $limit) {
                throw ValidationException::withMessages([
                    'attempts' => "You've used all {$limit} attempts for the AI Readiness Interview.",
                ]);
            }

            $blueprint = $this->blueprintFor($candidate);

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

            return $interview;
        });
    }

    /**
     * One blueprint per student, refreshed from their current CV on every
     * fresh attempt (not just created once and left stale) — a retake
     * after updating the CV should be interviewed on the CV as it stands
     * today, not the one from their first attempt.
     */
    private function blueprintFor(User $candidate): MockBlueprint
    {
        $profile = CvProfile::dataFor($candidate);
        $skills = array_values(array_filter(array_map('strval', $profile['skills'] ?? [])));
        $latestRole = $profile['experience'][0]['title'] ?? null;

        return MockBlueprint::query()->updateOrCreate(
            ['tenant_id' => $candidate->tenant_id, 'user_id' => $candidate->id],
            [
                'role_title' => is_string($latestRole) && $latestRole !== '' ? mb_substr($latestRole, 0, 255) : 'General technical readiness',
                'skill' => null,
                'competencies' => array_slice($skills, 0, 8) ?: ['problem solving', 'communication'],
                'opening_question' => "Let's start broad — walk me through your background and the kind of role you're aiming for.",
                'max_questions' => 15,
                'is_active' => false, // Hidden from course blueprint pickers.
            ],
        );
    }
}
