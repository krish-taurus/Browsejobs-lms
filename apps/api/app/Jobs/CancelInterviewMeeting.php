<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterviewInvite;
use App\Support\Interviews\Bots\MeetingBotClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** The candidate declined: call off the meeting bot and remove the Zoom meeting. */
final class CancelInterviewMeeting implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $inviteId) {}

    public function handle(ZoomClient $zoom, MeetingBotClient $bots): void
    {
        $invite = InterviewInvite::withoutGlobalScopes()->find($this->inviteId);
        if ($invite === null) {
            return;
        }

        if ($invite->bot_id !== null) {
            rescue(fn () => $bots->cancel($invite->bot_id), report: false);
        }
        if ($invite->zoom_meeting_id !== null) {
            rescue(fn () => $zoom->deleteMeeting($invite->zoom_meeting_id), report: false);
        }

        $invite->update(['zoom_meeting_id' => null, 'zoom_join_url' => null, 'bot_id' => null, 'bot_status' => 'cancelled']);
    }
}
