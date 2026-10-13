<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Jobs\SendInterviewInviteMessage;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\InterviewInvite;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use Illuminate\Support\Str;

/**
 * Ask one candidate to pick a slot for a later AI round (L2 …) on WhatsApp,
 * exactly like the L1 invite: three working days, their reply books it, the
 * link goes out on the day. Used when a round is cleared (automatically, or
 * on the client's YES when they approve each round).
 *
 * Idempotent: someone already asked for this round is never asked twice.
 */
final readonly class InviteToNextRound
{
    public function __construct(private InterviewTemplates $templates) {}

    /** The new invite, or null when they were already asked or have no WhatsApp number. */
    public function handle(EmployerJob $job, EmployerJobApplication $application, string $roundKey, ?User $actor = null): ?InterviewInvite
    {
        $exists = InterviewInvite::query()
            ->where('employer_job_application_id', $application->id)
            ->where('round', $roundKey)
            ->exists();
        if ($exists) {
            return null;
        }

        // The number they booked their last round on; else the one on their account.
        $previous = InterviewInvite::query()
            ->where('employer_job_application_id', $application->id)
            ->where('round', '!=', InterviewInvite::ROUND_SCREEN)
            ->latest('id')
            ->first();
        $phone = $previous?->phone ?? SendInterviewInvites::whatsappNumber($application->candidate()->withoutGlobalScopes()->value('phone'));
        if ($phone === null) {
            return null;
        }

        $invite = InterviewInvite::query()->create([
            'tenant_id' => $application->tenant_id,
            'employer_job_id' => $job->id,
            'employer_job_application_id' => $application->id,
            'candidate_user_id' => $application->candidate_id,
            'round' => $roundKey,
            'phone' => $phone,
            'status' => InterviewInvite::PENDING,
            'date_options' => SendInterviewInvites::dateOptions(now('Asia/Kolkata')),
            'token' => Str::random(40),
            'sent_by_user_id' => $actor?->id,
        ]);

        if ($this->templates->approved(InterviewTemplates::NEXT_ROUND)) {
            SendInterviewInviteMessage::dispatch($invite->id);
        } else {
            // interviews:open-due sends it as soon as Meta approves the message.
            $invite->update(['error' => 'Waiting for Meta to approve the next-round WhatsApp message.']);
        }

        return $invite;
    }
}
