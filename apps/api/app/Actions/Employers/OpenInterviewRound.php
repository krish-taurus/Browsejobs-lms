<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Models\InterviewInvite;
use App\Support\Employers\InterviewProcess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The booked day has come: move the candidate to the round's stage and send
 * the round (the existing SendInterviewRound — same question selection,
 * audit and EmployerInterviewInvited event as a manual send). The invite
 * listener then WhatsApps them the link.
 *
 * Never throws: a JD with the round switched off or no question bank is
 * recorded on the invite for the employer to see.
 */
final readonly class OpenInterviewRound
{
    public function __construct(
        private InterviewProcess $process,
        private MoveApplicationStage $move,
        private SendInterviewRound $send,
    ) {}

    public function handle(InterviewInvite $invite): void
    {
        $application = $invite->application()->withoutGlobalScopes()->first();
        if ($application === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($invite, $application): void {
            $job = $application->job;
            $round = $job !== null ? $this->process->round($job, $invite->round) : null;
            if ($round === null || ! $round->enabled || ! $round->isPlatformRun()) {
                $invite->update(['status' => InterviewInvite::FAILED, 'error' => 'The '.strtoupper($invite->round).' round is switched off for this role.']);

                return;
            }

            try {
                $target = EmployerApplicationStage::from($invite->round);
                if ($application->stage->canAdvanceTo($target) && $application->stage !== $target) {
                    $this->move->handle($application, $target, null, 'Interview day booked on WhatsApp', 'system');
                }

                $interview = $this->send->handle($application->fresh(), $round);
                $invite->update(['employer_interview_id' => $interview->id]);
            } catch (ValidationException $e) {
                $invite->update(['status' => InterviewInvite::FAILED, 'error' => (string) collect($e->errors())->flatten()->first()]);
            } catch (Throwable $e) {
                report($e);
                $invite->update(['status' => InterviewInvite::FAILED, 'error' => 'Could not open the interview. Try again from the pipeline.']);
            }
        });
    }
}
