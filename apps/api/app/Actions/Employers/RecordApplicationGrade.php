<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Events\ApplicationGraded;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;
use App\Support\Tenancy\TenantContext;

/**
 * PRD-E F3 — attach a completed, scored JD mock to an application and
 * promote it Applied → Graded. Grading is structurally firewalled from
 * monetisation: this action reads only the MockInterview scorecard and
 * has no dependency on billing state (ADR 0051 / PRD-E §8.9).
 *
 * Idempotent: re-recording the same interview is a no-op; a better
 * score from a newer attempt replaces the old one (best-attempt-wins,
 * candidate-favouring).
 */
final readonly class RecordApplicationGrade
{
    public function handle(EmployerJobApplication $application, MockInterview $interview): EmployerJobApplication
    {
        return app(TenantContext::class)->run($application->tenant, function () use ($application, $interview): EmployerJobApplication {
            $score = (int) ($interview->overall_score ?? 0);

            if ($application->mock_interview_id === $interview->id) {
                return $application;
            }

            if ($application->mock_score !== null && $score <= $application->mock_score) {
                return $application;
            }

            $wasUngraded = $application->graded_at === null;

            $application->update([
                'mock_interview_id' => $interview->id,
                'mock_score' => $score,
                'graded_at' => now(),
                'stage' => $application->stage === EmployerApplicationStage::Applied
                    ? EmployerApplicationStage::Graded->value
                    : $application->stage->value,
            ]);

            if ($wasUngraded) {
                $application->transitions()->create([
                    'from_stage' => EmployerApplicationStage::Applied->value,
                    'to_stage' => EmployerApplicationStage::Graded->value,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'note' => 'JD mock completed and scored',
                    'occurred_at' => now(),
                ]);
            }

            ApplicationGraded::dispatch($application->fresh());

            return $application->fresh();
        });
    }
}
