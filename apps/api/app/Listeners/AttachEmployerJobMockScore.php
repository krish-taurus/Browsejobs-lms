<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\RecordApplicationGrade;
use App\Events\MockCompleted;
use App\Models\EmployerJobApplication;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When a candidate finishes a mock that was spun from an employer JD, the
 * score becomes that application's grade (PRD-E F3).
 *
 * Best attempt wins — RecordApplicationGrade ignores a weaker retake — so a
 * candidate can keep practising against the same JD while their package
 * holds without ever damaging the score an employer already saw.
 *
 * Mocks not tied to an employer JD (course practice, job-feed quick mocks)
 * fall straight through.
 */
final class AttachEmployerJobMockScore implements ShouldQueue
{
    public function __construct(private readonly RecordApplicationGrade $grade) {}

    public function handle(MockCompleted $event): void
    {
        $jobId = $event->interview->blueprint?->employer_job_id;

        if ($jobId === null) {
            return;
        }

        $application = EmployerJobApplication::query()
            ->where('employer_job_id', $jobId)
            ->where('candidate_id', $event->user->id)
            ->first();

        if ($application === null) {
            return;
        }

        $this->grade->handle($application, $event->interview);
    }
}
