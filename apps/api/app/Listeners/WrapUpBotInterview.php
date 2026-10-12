<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MockCompleted;
use App\Jobs\FetchInterviewBotRecording;
use App\Models\EmployerInterview;
use App\Models\InterviewInvite;
use App\Support\Interviews\Bots\MeetingBotClient;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * An L1/L2 interview taken on Zoom by the meeting bot has finished: the bot
 * leaves the call, and the call's recording is fetched onto the interview a
 * few minutes later, once the provider has processed it.
 */
final class WrapUpBotInterview implements ShouldQueue
{
    public function __construct(private readonly MeetingBotClient $bots) {}

    public function handle(MockCompleted $event): void
    {
        if ($event->interview->blueprint?->employer_job_round_id === null) {
            return;
        }

        $interviewId = EmployerInterview::withoutGlobalScopes()->where('mock_interview_id', $event->interview->id)->value('id');
        $invite = $interviewId !== null
            ? InterviewInvite::withoutGlobalScopes()->where('employer_interview_id', $interviewId)->whereNotNull('bot_id')->first()
            : null;
        if ($invite === null) {
            return;
        }

        rescue(fn () => $this->bots->leave((string) $invite->bot_id), report: false);
        $invite->update(['bot_status' => 'done']);

        FetchInterviewBotRecording::dispatch($invite->id, $event->interview->id)->delay(now()->addMinutes(5));
    }
}
