<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LiveSession;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a rescheduled session's new time to Zoom (queued Zoom API call).
 */
final class UpdateZoomMeeting implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $liveSessionId) {}

    public function handle(ZoomClient $zoom): void
    {
        $session = LiveSession::query()->withoutGlobalScopes()->find($this->liveSessionId);

        if ($session === null || $session->zoom_meeting_id === null) {
            return;
        }

        $zoom->updateMeeting(
            $session->zoom_meeting_id,
            $session->scheduled_start,
            (int) ceil($session->plannedSeconds() / 60),
        );
    }
}
