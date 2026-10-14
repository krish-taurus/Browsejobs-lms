<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\InterviewInvite;
use App\Support\Interviews\Bots\MeetingBotClient;
use Illuminate\Support\Carbon;

/**
 * "I want to do it now": take a booked (or still unbooked) L1/L2 interview
 * straight away instead of waiting for the slot. Asked by the candidate
 * ("now", "today", "abhi") or by the client for the people who booked.
 *
 *  - In the BrowseJobs room: the round opens at once and the interview link
 *    is returned.
 *  - On Zoom (meeting bot configured): the invite is moved to the next free
 *    half-hour at least ten minutes from now; the caller then says so and
 *    dispatches BookInterviewMeeting to move the meeting and the AI interviewer.
 *
 * Nothing is half-done: if the round cannot be opened the invite goes back to
 * how it was, with the reason on it for the monitor.
 */
final readonly class OpenInterviewNow
{
    public function __construct(private OpenInterviewRound $open, private MeetingBotClient $bots) {}

    /**
     * @return array{mode: 'room'|'zoom'|'none', link: string|null, at: Carbon|null}
     */
    public function handle(InterviewInvite $invite): array
    {
        $none = ['mode' => 'none', 'link' => null, 'at' => null];
        $before = $invite->status;
        if (! in_array($before, [InterviewInvite::SENT, InterviewInvite::SCHEDULED], true)) {
            return $none;
        }

        $now = now('Asia/Kolkata');

        if ($this->bots->enabled()) {
            $at = SendInterviewInvites::freeZoomSlot($now->toDateString(), '00:00', $invite->id, $now->copy()->addMinutes(10)->startOfMinute());
            $invite->update(['status' => InterviewInvite::SCHEDULED, 'chosen_date' => $now->toDateString(), 'scheduled_at' => $at, 'error' => null]);

            return ['mode' => 'zoom', 'link' => null, 'at' => $at];
        }

        $invite->update(['status' => InterviewInvite::SCHEDULED, 'chosen_date' => $now->toDateString(), 'scheduled_at' => $now, 'error' => null]);
        $this->open->handle($invite);
        $invite->refresh();

        if ($invite->employer_interview_id === null) {
            // Could not open it: back to how it was, keeping the reason for the monitor.
            $invite->update(['status' => $before, 'scheduled_at' => null, 'chosen_date' => null, 'error' => $invite->error ?: 'Could not open the interview now.']);

            return $none;
        }

        if ($invite->status !== InterviewInvite::OPEN) {
            $invite->update(['status' => InterviewInvite::OPEN, 'opened_at' => now()]);
        }

        return ['mode' => 'room', 'link' => $invite->link(), 'at' => $now];
    }
}
