<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\SendInterviewRound;
use App\Events\ApplicationGraded;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerJobApplication;
use App\Models\EmployerJobRound;
use App\Support\Employers\InterviewProcess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F18: rounds an employer set to go out on their own.
 *
 * "Auto at 75" means: when a score lands, the next enabled round that is set
 * to automatic sends itself if that score clears its bar. It listens to both
 * the JD interview being graded and a round being graded, because the chain
 * has to start somewhere and then keep going.
 *
 * This deliberately does not move the candidate's stage. Stage changes are
 * the automation rules engine's job (F6) and are audited as decisions;
 * sending a round is just asking a question, and quietly promoting someone
 * as a side effect of that would hide the decision from the people reviewing
 * the pipeline.
 */
final class DispatchAutomaticInterviewRounds implements ShouldQueue
{
    public function __construct(
        private readonly InterviewProcess $process,
        private readonly SendInterviewRound $send,
    ) {}

    /** The JD interview landed — this is where the chain can start. */
    public function handleApplicationGraded(ApplicationGraded $event): void
    {
        $this->dispatchNext($event->application, $event->application->mock_score, null);
    }

    /** A round landed — continue from it. */
    public function handleInterviewGraded(EmployerInterviewGraded $event): void
    {
        $this->dispatchNext(
            $event->interview->application()->withoutGlobalScopes()->first(),
            $event->interview->overall_score,
            $event->interview->round,
        );
    }

    private function dispatchNext(?EmployerJobApplication $application, ?int $score, ?string $afterKey): void
    {
        if ($application === null || $score === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($application, $score, $afterKey): void {
            $job = $application->job;

            if ($job === null) {
                return;
            }

            $next = $this->process->next($job, $afterKey);

            if ($next === null || ! $next->dispatchesAutomatically()) {
                return;
            }

            // Below the bar is a decision the employer already made when they
            // set the threshold. Nothing is sent and nothing is rejected —
            // the candidate simply waits for a human.
            if ($score < (int) $next->auto_min_score) {
                return;
            }

            $this->sendQuietly($application, $next);
        });
    }

    private function sendQuietly(EmployerJobApplication $application, EmployerJobRound $round): void
    {
        try {
            $this->send->handle($application, $round);
        } catch (ValidationException $e) {
            Log::info('Automatic interview round not sent', [
                'application_id' => $application->id,
                'round' => $round->key,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
