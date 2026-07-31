<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\SendInterviewRound;
use App\Enums\EmployerInterviewRound;
use App\Events\ApplicationStageChanged;
use App\Support\Employers\InterviewProcess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F5/F18: moving an application into the L1 or L2 stage sends that
 * round.
 *
 * The stage-to-round mapping stays because the pipeline stages are still
 * named L1 and L2. What changed is where the round's content comes from:
 * the employer's round definition rather than a straight copy of the JD
 * mock. Rounds an employer added beyond these two are not stage-linked and
 * go out via the send action, by hand or on a threshold.
 *
 * Idempotency lives in SendInterviewRound, so a repeated event is a no-op.
 */
final class CreateInterviewOnStageEntry implements ShouldQueue
{
    public function __construct(
        private readonly InterviewProcess $process,
        private readonly SendInterviewRound $send,
    ) {}

    public function handle(ApplicationStageChanged $event): void
    {
        $application = $event->application;
        $stageRound = EmployerInterviewRound::forStage($application->stage);

        if ($stageRound === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($application, $stageRound): void {
            $job = $application->job;

            if ($job === null) {
                return;
            }

            $round = $this->process->round($job, $stageRound->value);

            // An employer who deleted or disabled this round has said they do
            // not run it. Recreating it here would override that.
            if ($round === null || ! $round->enabled || ! $round->isPlatformRun()) {
                return;
            }

            try {
                $this->send->handle($application, $round);
            } catch (ValidationException $e) {
                // No question bank yet, or nothing left unasked. Both are
                // states the employer UI reports; neither is a failed job
                // worth retrying, and neither may invent an interview.
                Log::info('Interview round not sent on stage entry', [
                    'application_id' => $application->id,
                    'round' => $round->key,
                    'reason' => $e->getMessage(),
                ]);
            }
        });
    }
}
