<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Events\ApplicationReceived;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F3 — a candidate applies to a published JD. Applying is always
 * free (compliance: the paid mock pack buys preparation + ranked
 * advantage, never access). If the candidate has already completed the
 * JD-specific mock, grading attaches via RecordApplicationGrade.
 *
 * @see RecordApplicationGrade
 */
final readonly class ApplyToEmployerJob
{
    /** @param array<int, array<string, mixed>>|null $knockoutAnswers */
    public function handle(EmployerJob $job, User $candidate, ?array $knockoutAnswers = null): EmployerJobApplication
    {
        return app(TenantContext::class)->run($candidate->tenant, function () use ($job, $candidate, $knockoutAnswers): EmployerJobApplication {
            if ($job->status !== EmployerJobStatus::Published) {
                throw ValidationException::withMessages([
                    'job' => 'This role is not open for applications.',
                ]);
            }

            $exists = EmployerJobApplication::where('employer_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'job' => 'You have already applied to this role.',
                ]);
            }

            $application = EmployerJobApplication::create([
                'employer_job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'jd_mock_id' => $job->currentMock()?->id,
                'stage' => EmployerApplicationStage::Applied->value,
                'knockout_answers' => $knockoutAnswers,
            ]);

            $application->transitions()->create([
                'from_stage' => null,
                'to_stage' => EmployerApplicationStage::Applied->value,
                'actor_type' => 'user',
                'actor_id' => $candidate->id,
                'occurred_at' => now(),
            ]);

            ApplicationReceived::dispatch($application);

            return $application;
        });
    }
}
