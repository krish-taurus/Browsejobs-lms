<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\MoveApplicationStage;
use App\Actions\Employers\SendInterviewInvites;
use App\Enums\EmployerApplicationStage;
use App\Events\EmployerInterviewGraded;
use App\Jobs\SendHiringResult;
use App\Jobs\SendInterviewInviteMessage;
use App\Models\InterviewInvite;
use App\Support\Audit\AuditLogger;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\InterviewTemplates;
use App\Support\Employers\ShortlistSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * A round the message agent booked (L1, L2 …) has been scored. At or above
 * the JD's round_pass_mark:
 *  - another AI round follows → WhatsApp the candidate to pick a slot for it
 *    (the next-round template), exactly like the L1 invite;
 *  - that was the last AI round → the candidate has cleared the AI interviews:
 *    move them to the human round and send the client the result.
 * Below the mark nothing is sent; the client sees the score on the pipeline.
 *
 * Rounds sent by hand from the pipeline (no invite) are left to the
 * employer's own automation (DispatchAutomaticInterviewRounds).
 */
final class BookNextInterviewRound implements ShouldQueue
{
    public function __construct(
        private readonly InterviewProcess $process,
        private readonly InterviewTemplates $templates,
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

        app(TenantContext::class)->run($application->tenant, function () use ($interview, $invite, $application): void {
            $job = $application->job;
            if ($job === null) {
                return;
            }

            $passMark = ShortlistSettings::forJob($job)->roundPassMark;
            if ((int) $interview->overall_score < $passMark) {
                $this->audit->log('employer.interview_round_not_cleared', $application, [
                    'round' => $interview->round, 'score' => $interview->overall_score, 'pass_mark' => $passMark,
                ]);

                return;
            }

            $next = $this->process->next($job, $interview->round);

            if ($next !== null && $next->isPlatformRun()) {
                $exists = InterviewInvite::query()
                    ->where('employer_job_application_id', $application->id)
                    ->where('round', $next->key)
                    ->exists();
                if ($exists) {
                    return;
                }

                $nextInvite = InterviewInvite::query()->create([
                    'tenant_id' => $application->tenant_id,
                    'employer_job_id' => $job->id,
                    'employer_job_application_id' => $application->id,
                    'candidate_user_id' => $invite->candidate_user_id,
                    'round' => $next->key,
                    'phone' => $invite->phone,
                    'status' => InterviewInvite::PENDING,
                    'date_options' => SendInterviewInvites::dateOptions(now('Asia/Kolkata')),
                    'token' => Str::random(40),
                ]);

                if ($this->templates->approved(InterviewTemplates::NEXT_ROUND)) {
                    SendInterviewInviteMessage::dispatch($nextInvite->id);
                } else {
                    // interviews:open-due sends it as soon as Meta approves the message.
                    $nextInvite->update(['error' => 'Waiting for Meta to approve the next-round WhatsApp message.']);
                }

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
