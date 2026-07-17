<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MentorSession;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued Zoom maintenance for a mentor session: `update` pushes the new
 * start time after a reschedule; `delete` removes the meeting after a
 * cancel. Zoom being briefly down never breaks the user-facing flow.
 */
final class SyncMentorZoom implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $action,
    ) {}

    public function handle(ZoomClient $zoom): void
    {
        $session = MentorSession::query()->withoutGlobalScopes()->find($this->sessionId);
        if ($session === null || $session->zoom_meeting_id === null) {
            return;
        }

        try {
            match ($this->action) {
                'update' => $zoom->updateMeeting($session->zoom_meeting_id, $session->starts_at->toImmutable(), $session->duration_minutes),
                'delete' => $zoom->deleteMeeting($session->zoom_meeting_id),
                default => null,
            };
        } catch (Throwable) {
            $this->release(300); // Zoom hiccup — retry in five minutes.
        }
    }
}
