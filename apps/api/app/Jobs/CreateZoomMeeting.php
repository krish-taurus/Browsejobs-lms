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
 * Creates the Zoom meeting for a scheduled session and stores its identifiers.
 * Idempotent + retry-safe: if the meeting already exists it does nothing.
 * Runs without a tenant context (queued), so it bypasses the tenant scope.
 */
final class CreateZoomMeeting implements ShouldQueue
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

        if ($session === null || $session->zoom_meeting_id !== null) {
            return;
        }

        $meeting = $zoom->createMeeting(
            $session->title,
            $session->scheduled_start,
            (int) ceil($session->plannedSeconds() / 60),
        );

        $session->update([
            'zoom_meeting_id' => $meeting->id,
            'zoom_join_url' => $meeting->joinUrl,
            'zoom_start_url' => $meeting->startUrl,
        ]);
    }
}
