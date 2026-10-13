<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\InviteToNextRound;
use App\Actions\Employers\MoveApplicationStage;
use App\Actions\Employers\ProposeHiringStep;
use App\Enums\EmployerApplicationStage;
use App\Events\EmployerInterviewGraded;
use App\Jobs\SendClientUpdate;
use App\Jobs\SendHiringResult;
use App\Models\InterviewInvite;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\ShortlistSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A round the message agent booked (L1, L2 …) has been scored. At or above
 * the JD's round_pass_mark:
 *  - another AI round follows → WhatsApp the candidate to pick a slot for it
 *    (the next-round template), exactly like the L1 invite;
 *  - that was the last AI round → the candidate has cleared the AI interviews:
 *    move them to the human round and send the client the result.
 * Below the mark nothing is sent; the client sees the score on the pipeline.
 *
 * When the client approves each round (confirm_rounds), a pass invites
 * nobody: Taurus proposes the next round for everyone who cleared this one,
 * and the invites go out on the client's YES.
 *
 * Rounds sent by hand from the pipeline (no invite) are left to the
 * employer's own automation (DispatchAutomaticInterviewRounds).
 */
final class BookNextInterviewRound implements ShouldQueue
{
    public function __construct(
        private readonly InterviewProcess $process,
        private readonly InviteToNextRound $invite,
        private readonly ProposeHiringStep $propose,
        private readonly MoveApplicationStage $move,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(EmployerInterviewGraded $event): void
    {
        $interview = $event->interview;
        $invite = InterviewInvite::withoutGlobalScopes()->where('employer_interview_id', $interview->id)->first();
        if ($invite === null || $interview->overall_score === null) {
            return;
        }

        $application = $interview->application()->withoutGlobalScopes()->first();
        if ($application === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($interview, $application): void {
            $job = $application->job;
            if ($job === null) {
                return;
            }

            $passMark = ShortlistSettings::forJob($job)->roundPassMark;
            $name = (string) ($application->candidate()->withoutGlobalScopes()->value('name') ?? 'A candidate');
            $round = strtoupper((string) $interview->round);
            if ((int) $interview->overall_score < $passMark) {
                $this->audit->log('employer.interview_round_not_cleared', $application, [
                    'round' => $interview->round, 'score' => $interview->overall_score, 'pass_mark' => $passMark,
                ]);
                SendClientUpdate::dispatch($job->id, "❌ {$name} scored {$interview->overall_score}/100 in the {$round} AI interview, below your pass mark of {$passMark}.");

                return;
            }

            $next = $this->process->next($job, $interview->round);

            if ($next !== null && $next->isPlatformRun()) {
                $settings = ShortlistSettings::forJob($job);
                if ($settings->confirmRounds) {
                    SendClientUpdate::dispatch($job->id, "✅ {$name} scored {$interview->overall_score}/100 in the {$round} AI interview and passed.");
                    $this->propose->nextRound($job, $next->key);

                    return;
                }

                if ($this->invite->handle($job, $application, $next->key) === null) {
                    return;
                }

                SendClientUpdate::dispatch($job->id, "✅ {$name} scored {$interview->overall_score}/100 in the {$round} AI interview and passed. Asked to book the ".strtoupper($next->key).' interview.');

                return;
            }

            // The last AI round is cleared.
            if ($application->stage->canAdvanceTo(EmployerApplicationStage::HumanRound) && $application->stage !== EmployerApplicationStage::HumanRound) {
                $this->move->handle($application, EmployerApplicationStage::HumanRound, null, 'Cleared the AI interview rounds', 'system');
            }
            SendHiringResult::dispatch($application->id);
        });
    }
}
