<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Jobs\CreateZoomMeeting;
use App\Models\LiveSession;
use App\Models\ZoomLicense;
use App\Support\Zoom\ZoomClient;

/**
 * Creates the Zoom meeting for a session (if it doesn't have one) and stores its
 * identifiers. Shared by the queued {@see CreateZoomMeeting} and the
 * synchronous "create meeting" repair endpoint. Idempotent: a session that already
 * has a meeting is returned unchanged.
 */
final readonly class EnsureZoomMeeting
{
    public function handle(LiveSession $session, ZoomClient $zoom): LiveSession
    {
        if ($session->zoom_meeting_id !== null) {
            return $session;
        }

        $session->loadMissing('batch:id,trainer_id');

        // Host under the batch trainer's allocated Zoom license, if any — so concurrent
        // classes run on different licenses. Otherwise fall back to the configured
        // default host (Server-to-Server OAuth has no "me" user), then to "me".
        $hostUserId = null;
        $trainerId = $session->batch?->trainer_id;
        if ($trainerId !== null) {
            $hostUserId = ZoomLicense::query()->withoutGlobalScopes()
                ->where('mentor_id', $trainerId)
                ->where('active', true)
                ->value('zoom_user_id');
        }

        $default = config('services.zoom.default_host_id');
        $hostUserId = $hostUserId ?: (is_string($default) && $default !== '' ? $default : null);

        $meeting = $zoom->createMeeting(
            $session->title,
            $session->scheduled_start,
            (int) ceil($session->plannedSeconds() / 60),
            $hostUserId,
            (bool) $session->auto_record,
        );

        $session->update([
            'zoom_meeting_id' => $meeting->id,
            'zoom_join_url' => $meeting->joinUrl,
            'zoom_start_url' => $meeting->startUrl,
        ]);

        return $session;
    }
}
