<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MockCompleted;
use App\Jobs\RunJobShortlistAgent;
use App\Models\InterviewInvite;
use App\Models\JobShortlistRun;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Someone the shortlist agent sent the AI screening interview to has finished
 * the JD's interview: close their invite and run the agent again, so they are
 * shortlisted (and, with auto_invite, sent the L1 invite) if CV and interview
 * both clear the bar. Interviews nobody was invited to fall straight through.
 */
final class RerunShortlistAfterScreening implements ShouldQueue
{
    public function handle(MockCompleted $event): void
    {
        $jobId = $event->interview->blueprint?->employer_job_id;
        if ($jobId === null) {
            return;
        }

        $closed = InterviewInvite::withoutGlobalScopes()
            ->where('employer_job_id', $jobId)
            ->where('candidate_user_id', $event->user->id)
            ->where('round', InterviewInvite::ROUND_SCREEN)
            ->whereIn('status', InterviewInvite::LISTENING)
            ->update(['status' => InterviewInvite::DONE]);

        if ($closed > 0) {
            RunJobShortlistAgent::dispatch($jobId, JobShortlistRun::TRIGGER_SCREENING);
        }
    }
}
