<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerInterviewStatus;
use App\Jobs\CancelInterviewMeeting;
use App\Jobs\SendInterviewInviteMessage;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\InterviewInvite;
use App\Models\User;
use App\Support\Employers\InterviewTemplates;
use App\Support\Interviews\Bots\MeetingBotClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The client lets a candidate sit a round again ("retake L1 for Kunal").
 *
 * The first attempt is never erased: its score, dimension scores and recording
 * id are kept in the interview's `previous_attempts`, and the recording and
 * transcript stay on their own mock interview. The interview then goes back to
 * "invited" and the invite is reset to a fresh WhatsApp ("pick a day"), with a
 * new link, so the old link and the old booking cannot be used for the new
 * attempt. A booked Zoom meeting and its bot are called off first.
 *
 * Someone never invited to the round has nothing to retake (null), and so does
 * anyone already hired or rejected.
 */
final readonly class RetakeInterviewRound
{
    public function __construct(private InterviewTemplates $templates) {}

    /** The reset invite, or null when there was nothing to retake. */
    public function handle(EmployerJob $job, EmployerJobApplication $application, string $roundKey, ?User $actor = null): ?InterviewInvite
    {
        if ($application->stage->isTerminal()) {
            return null;
        }

        $invite = InterviewInvite::query()
            ->where('employer_job_application_id', $application->id)
            ->where('round', $roundKey)
            ->first();
        if ($invite === null) {
            return null;
        }

        // A booked Zoom meeting and its bot go first (the cancel job reads the ids we are about to clear).
        if ($invite->zoom_meeting_id !== null || $invite->bot_id !== null) {
            rescue(fn () => (new CancelInterviewMeeting($invite->id))->handle(app(ZoomClient::class), app(MeetingBotClient::class)), report: false);
            $invite->refresh();
        }

        DB::transaction(function () use ($invite, $application, $roundKey, $actor): void {
            $interview = EmployerInterview::query()
                ->where('employer_job_application_id', $application->id)
                ->where('round', $roundKey)
                ->first();

            if ($interview !== null) {
                $attempts = (array) ($interview->previous_attempts ?? []);
                $attempts[] = array_filter([
                    'mock_interview_id' => $interview->mock_interview_id,
                    'status' => $interview->status->value,
                    'overall_score' => $interview->overall_score,
                    'dimension_scores' => $interview->dimension_scores,
                    'graded_at' => $interview->graded_at?->toIso8601String(),
                    'retaken_at' => now()->toIso8601String(),
                    'retaken_by' => $actor?->id,
                ], fn ($value) => $value !== null);

                $interview->update([
                    'previous_attempts' => $attempts,
                    'status' => EmployerInterviewStatus::Invited->value,
                    'mock_interview_id' => null,
                    'overall_score' => null,
                    'dimension_scores' => null,
                    'grading_summary' => null,
                    'grading_source' => null,
                    'answers' => null,
                    'invited_at' => now(),
                    'started_at' => null,
                    'submitted_at' => null,
                    'graded_at' => null,
                    // Long enough for them to pick a day (up to three working days out) and take it.
                    'expires_at' => now()->addDays(10),
                ]);
            }

            $invite->update([
                'status' => InterviewInvite::PENDING,
                'date_options' => SendInterviewInvites::dateOptions(now('Asia/Kolkata')),
                'chosen_date' => null,
                'scheduled_at' => null,
                'token' => Str::random(40),
                'wamid' => null,
                'error' => null,
                'zoom_meeting_id' => null,
                'zoom_join_url' => null,
                'bot_code' => null,
                'bot_id' => null,
                'bot_status' => null,
                'sent_at' => null,
                'replied_at' => null,
                'opened_at' => null,
                'reminded_at' => null,
                'nudged_at' => null,
                'chased_at' => null,
                'sent_by_user_id' => $actor?->id,
            ]);
        });

        $template = $invite->round === 'l1' ? InterviewTemplates::INVITE : InterviewTemplates::NEXT_ROUND;
        if ($this->templates->approved($template)) {
            SendInterviewInviteMessage::dispatch($invite->id);
        } else {
            // interviews:open-due sends it as soon as Meta approves the message.
            $invite->update(['error' => 'Waiting for Meta to approve the interview WhatsApp message.']);
        }

        return $invite->fresh();
    }
}
