<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EmployerInterviewRound;
use App\Enums\EmployerInterviewStatus;
use App\Events\ApplicationStageChanged;
use App\Events\EmployerInterviewInvited;
use App\Models\EmployerInterview;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * PRD-E F5 automation: moving an application into L1 or L2 creates that
 * round's interview invite with a question snapshot from the JD mock.
 * Idempotent via the (application, round) unique constraint — a repeat
 * event is a no-op.
 */
final class CreateInterviewOnStageEntry implements ShouldQueue
{
    public function handle(ApplicationStageChanged $event): void
    {
        $application = $event->application;
        $round = EmployerInterviewRound::forStage($application->stage);

        if ($round === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($application, $round): void {
            $exists = EmployerInterview::where('employer_job_application_id', $application->id)
                ->where('round', $round->value)
                ->exists();

            if ($exists) {
                return;
            }

            $mock = $application->jdMock ?? $application->job?->currentMock();
            if ($mock === null || $mock->questions === null) {
                return; // No question source yet — surfaced in the employer UI as "mock not ready".
            }

            $interview = EmployerInterview::create([
                'employer_job_application_id' => $application->id,
                'round' => $round->value,
                'status' => EmployerInterviewStatus::Invited->value,
                'question_set' => $mock->questions,
                'rubric' => $mock->rubric ?? ['dimensions' => []],
                'invited_at' => now(),
                'expires_at' => now()->addHours((int) config('employers.interview_window_hours')),
            ]);

            EmployerInterviewInvited::dispatch($interview);
        });
    }
}
