<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\BatchMemberStatus;
use App\Enums\LiveSessionStatus;
use App\Jobs\DeleteZoomMeeting;
use App\Models\LiveSession;
use App\Models\SessionChange;
use App\Models\User;
use App\Support\Notifications\SessionNotifier;
use Illuminate\Support\Str;

/**
 * Cancels a live session (PRD §6.3): deletes the Zoom meeting, notifies the
 * whole batch instantly, logs the change, and voids the reminder ladder by
 * bumping the reminder token (pending reminder jobs then no-op).
 */
final readonly class CancelLiveSession
{
    public function __construct(private SessionNotifier $notifier) {}

    public function handle(LiveSession $session, string $reason, ?User $actor = null): void
    {
        $previousStart = $session->scheduled_start->copy();

        $session->update([
            'status' => LiveSessionStatus::Cancelled->value,
            'reminder_token' => (string) Str::uuid(),
        ]);

        if ($session->zoom_meeting_id !== null) {
            DeleteZoomMeeting::dispatch($session->zoom_meeting_id);
        }

        SessionChange::query()->create([
            'tenant_id' => $session->tenant_id,
            'live_session_id' => $session->id,
            'actor_id' => $actor?->id,
            'type' => 'cancelled',
            'reason' => $reason,
            'old_start' => $previousStart,
        ]);

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $session->batch->members()->whereIn('status', $occupying)->with('student')->get()
            ->each(fn ($member) => $this->notifier->cancelled($member->student, $session, $reason));

        // Keep the trainer in the loop too — not just the students.
        if (($trainer = $session->batch->trainer) !== null) {
            $this->notifier->trainerCancelled($trainer, $session, $reason);
        }
    }
}
