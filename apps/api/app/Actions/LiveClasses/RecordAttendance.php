<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Models\Attendance;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Turns Zoom participant join/leave webhooks into attendance records. Handles
 * multiple join/leave cycles per student by accumulating time between each
 * paired join and leave. Lateness is decided on the first join.
 */
final readonly class RecordAttendance
{
    public function participantJoined(LiveSession $session, User $user, CarbonInterface $at): Attendance
    {
        $attendance = Attendance::query()->firstOrNew([
            'live_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        if (! $attendance->exists) {
            $attendance->tenant_id = $session->tenant_id;
            $attendance->first_joined_at = $at;
            $graceMinutes = (int) config('services.zoom.late_grace_minutes', 10);
            $attendance->is_late = $at->greaterThan($session->scheduled_start->copy()->addMinutes($graceMinutes));
        }

        $attendance->open_join_at = $at;
        $attendance->save();

        return $attendance;
    }

    public function participantLeft(LiveSession $session, User $user, CarbonInterface $at): void
    {
        $attendance = Attendance::query()
            ->where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($attendance === null || $attendance->open_join_at === null) {
            return;
        }

        $attendance->total_seconds += max(0, (int) $attendance->open_join_at->diffInSeconds($at));
        $attendance->open_join_at = null;
        $attendance->last_left_at = $at;
        $attendance->attended_pct = (int) min(100, round($attendance->total_seconds / $session->plannedSeconds() * 100));
        $attendance->save();
    }
}
