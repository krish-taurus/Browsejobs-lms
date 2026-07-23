<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\LiveSessionStatus;
use App\Jobs\CreateZoomMeeting;
use App\Models\LiveSession;
use App\Models\ZoomLicense;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Facades\DB;

/**
 * Creates the Zoom meeting for a session (if it doesn't have one) and stores its
 * identifiers. Shared by the queued {@see CreateZoomMeeting} and the
 * synchronous "create meeting" repair endpoint. Idempotent: a session that already
 * has a meeting is returned unchanged.
 *
 * Licenses auto-rotate (ADR 0043): nothing is dedicated to a trainer. The session
 * claims any active pool license that has no other claimed session overlapping its
 * time — so whoever is teaching at 10:00 gets a free host, and the same license
 * serves a different trainer at 14:00. All licenses busy (or none configured)
 * falls back to the configured default host.
 */
final readonly class EnsureZoomMeeting
{
    public function handle(LiveSession $session, ZoomClient $zoom): LiveSession
    {
        if ($session->zoom_meeting_id !== null) {
            return $session;
        }

        $hostUserId = $this->claimLicense($session);

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

    /**
     * Claim a pool license that is free for this session's time window and
     * remember it on the session (`zoom_license_id` doubles as the rotation
     * lock). A retried job reuses its earlier claim, and a cancelled session
     * stops counting as a conflict — its license is simply picked again.
     */
    private function claimLicense(LiveSession $session): ?string
    {
        if ($session->zoom_license_id !== null) {
            return ZoomLicense::query()->withoutGlobalScopes()
                ->whereKey($session->zoom_license_id)->value('zoom_user_id');
        }

        return DB::transaction(function () use ($session): ?string {
            $licenses = ZoomLicense::query()->withoutGlobalScopes()
                ->where('tenant_id', $session->tenant_id)
                ->where('active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'zoom_user_id']);

            if ($licenses->isEmpty()) {
                return null;
            }

            $start = $session->scheduled_start;
            $end = $session->scheduled_end ?? $start->copy()->addSeconds($session->plannedSeconds());

            // Sessions that could overlap ours: claimed, still live/scheduled, and
            // starting near our window (classes are capped at 8h, so anything that
            // started earlier than that is over by our start).
            $busyLicenseIds = LiveSession::query()->withoutGlobalScopes()
                ->where('tenant_id', $session->tenant_id)
                ->whereKeyNot($session->id)
                ->whereNotNull('zoom_license_id')
                ->whereIn('status', [LiveSessionStatus::Scheduled->value, LiveSessionStatus::Live->value])
                ->where('scheduled_start', '<', $end)
                ->where('scheduled_start', '>', $start->copy()->subHours(8))
                ->get(['id', 'zoom_license_id', 'scheduled_start', 'scheduled_end'])
                ->filter(function (LiveSession $other) use ($start): bool {
                    $otherEnd = $other->scheduled_end ?? $other->scheduled_start->copy()->addSeconds($other->plannedSeconds());

                    return $otherEnd->gt($start);
                })
                ->pluck('zoom_license_id')
                ->all();

            $free = $licenses->first(fn (ZoomLicense $l) => ! in_array($l->id, $busyLicenseIds, true));

            if ($free === null) {
                return null;
            }

            $session->update(['zoom_license_id' => $free->id]);

            return $free->zoom_user_id;
        });
    }
}
