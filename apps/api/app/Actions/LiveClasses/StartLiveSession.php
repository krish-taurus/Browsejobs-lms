<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Gates hosting a live class: hands the Zoom host `start_url` (the one-click
 * "start as host" link) only to the staff member responsible for the class —
 * its assigned module trainer / lead trainer — or an admin, and only within a
 * window around class time. Raw host links are never exposed otherwise.
 */
final readonly class StartLiveSession
{
    public function handle(LiveSession $session, User $staff): string
    {
        if (! $this->mayHost($session, $staff)) {
            throw ValidationException::withMessages([
                'access' => 'Only the assigned trainer or an admin can start this class.',
            ]);
        }

        if (in_array($session->status, [LiveSessionStatus::Cancelled, LiveSessionStatus::Ended], true)) {
            throw ValidationException::withMessages([
                'session' => 'This class is not open to start.',
            ]);
        }

        if ($session->zoom_start_url === null) {
            throw ValidationException::withMessages([
                'session' => 'The Zoom meeting for this class is not ready yet — make sure a Zoom license is connected.',
            ]);
        }

        $start = $session->scheduled_start;
        $end = $session->scheduled_end ?? $start->copy()->addMinutes(intdiv($session->plannedSeconds(), 60));

        if (now()->lt($start->copy()->subMinutes(LiveSession::HOST_LEAD_MINUTES))) {
            throw ValidationException::withMessages([
                'timing' => 'You can start this class from '.LiveSession::HOST_LEAD_MINUTES.' minutes before it begins.',
            ]);
        }

        if (now()->gt($end->copy()->addMinutes(LiveSession::HOST_TRAIL_MINUTES))) {
            throw ValidationException::withMessages([
                'timing' => 'This class time has already passed.',
            ]);
        }

        return $session->zoom_start_url;
    }

    /**
     * The class's assigned teacher (module trainer, falling back to the lead) or
     * any admin / super-admin in the tenant may host.
     */
    private function mayHost(LiveSession $session, User $staff): bool
    {
        if ($staff->hasRole('admin', 'super-admin')) {
            return true;
        }

        $batch = $session->batch;
        $batch->loadMissing(['trainer', 'moduleTrainers.trainer']);
        $teacher = $batch->trainerForModule($session->topic?->module_id);

        return $teacher !== null && (int) $teacher->id === (int) $staff->id;
    }
}
