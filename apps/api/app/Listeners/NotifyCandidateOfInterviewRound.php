<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmployerInterviewInvited;
use App\Jobs\SendInterviewLinkMessage;
use App\Models\InterviewInvite;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * A round was sent to a candidate (booked L1 day, L2 after L1 cleared, or an
 * employer pressing send): WhatsApp them the link to take it — spoken, in the
 * interview room, recorded. Before this, nothing told the candidate at all.
 *
 * One thread per (application, round): a booked invite is reused, otherwise
 * one is made on the spot. A repeat event for the same interview is a no-op.
 */
final class NotifyCandidateOfInterviewRound implements ShouldQueue
{
    public function handle(EmployerInterviewInvited $event): void
    {
        $interview = $event->interview;

        app(TenantContext::class)->run($interview->tenant, function () use ($interview): void {
            $application = $interview->application()->withoutGlobalScopes()->with('candidate')->first();
            if ($application === null || $application->candidate === null) {
                return;
            }

            $invite = InterviewInvite::query()
                ->where('employer_job_application_id', $application->id)
                ->where('round', $interview->round)
                ->first();

            if ($invite !== null && $invite->employer_interview_id === $interview->id && in_array($invite->status, [InterviewInvite::OPEN, InterviewInvite::DONE], true)) {
                return;
            }

            $phone = preg_replace('/\D+/', '', (string) $application->candidate->phone) ?? '';
            if (strlen($phone) === 10) {
                $phone = '91'.$phone;
            }

            $invite ??= new InterviewInvite([
                'tenant_id' => $application->tenant_id,
                'employer_job_id' => $application->employer_job_id,
                'employer_job_application_id' => $application->id,
                'candidate_user_id' => $application->candidate_id,
                'round' => $interview->round,
                'token' => Str::random(40),
            ]);

            $invite->fill([
                'phone' => $invite->phone ?: $phone,
                'employer_interview_id' => $interview->id,
                'status' => InterviewInvite::OPEN,
                'chosen_date' => $invite->chosen_date ?? now('Asia/Kolkata')->toDateString(),
                'opened_at' => now(),
                'error' => strlen($phone) < 11 && ! $invite->phone ? 'No mobile number to send the link to.' : null,
            ])->save();

            if ($invite->error === null) {
                SendInterviewLinkMessage::dispatch($invite->id);
            }
        });
    }
}
