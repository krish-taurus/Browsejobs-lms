<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterviewInvite;
use App\Models\MockInterview;
use App\Support\Interviews\Bots\MeetingBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the Zoom call's recording (from the meeting bot) onto the AI
 * interview, where the employer's candidate page already plays it from.
 * The provider takes a few minutes to process a call, so this waits and
 * tries again for up to an hour.
 */
final class FetchInterviewBotRecording implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 12;

    public int $timeout = 600;

    public function __construct(public readonly int $inviteId, public readonly int $mockInterviewId) {}

    public function handle(MeetingBotClient $bots): void
    {
        $invite = InterviewInvite::withoutGlobalScopes()->find($this->inviteId);
        $mock = MockInterview::withoutGlobalScopes()->find($this->mockInterviewId);
        if ($invite === null || $invite->bot_id === null || $mock === null) {
            return;
        }

        $url = $bots->recordingUrl($invite->bot_id);
        if ($url === null) {
            $this->release(300);

            return;
        }

        $temp = tempnam(sys_get_temp_dir(), 'botrec');
        try {
            Http::timeout(540)->sink($temp)->get($url)->throw();

            $name = "{$mock->id}-zoom.mp4";
            Storage::disk('s3')->putFileAs("mock-recordings/{$mock->tenant_id}", new File($temp), $name);
            $mock->update(['recording_url' => "mock-recordings/{$mock->tenant_id}/{$name}"]);
            $invite->update(['bot_status' => 'recorded']);
        } finally {
            @unlink($temp);
        }
    }
}
