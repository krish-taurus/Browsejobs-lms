<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\BatchMemberStatus;
use App\Jobs\UpdateZoomMeeting;
use App\Models\LiveSession;
use App\Models\SessionChange;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use Carbon\CarbonInterface;

/**
 * Reschedules a live session (PRD §6.3): updates the Zoom meeting, notifies the
 * batch with old-vs-new time, logs the change, and re-arms the reminder ladder
 * for the new time — which voids the old ladder via a fresh reminder token.
 */
final readonly class RescheduleLiveSession
{
    public function __construct(
        private SessionNotifier $notifier,
        private ArmSessionReminders $armReminders,
    ) {}

    public function handle(
        LiveSession $session,
        CarbonInterface $newStart,
        ?CarbonInterface $newEnd,
        string $reason,
        ?User $actor = null,
    ): void {
        $previousStart = $session->scheduled_start->copy();

        $session->update([
            'scheduled_start' => $newStart,
            'scheduled_end' => $newEnd,
        ]);

        if ($session->zoom_meeting_id !== null) {
            UpdateZoomMeeting::dispatch($session->id);
        }

        SessionChange::query()->create([
            'tenant_id' => $session->tenant_id,
            'live_session_id' => $session->id,
            'actor_id' => $actor?->id,
            'type' => 'rescheduled',
            'reason' => $reason,
            'old_start' => $previousStart,
            'new_start' => $newStart,
        ]);

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $session->batch->members()->whereIn('status', $occupying)->with('student')->get()
            ->each(fn ($member) => $this->notifier->rescheduled($member->student, $session, $previousStart, $reason));

        // Re-arm for the new time; issues a new token so the old ladder no-ops.
        $this->armReminders->handle($session);
    }
}
