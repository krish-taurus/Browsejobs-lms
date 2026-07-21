<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\LiveSession;
use App\Models\User;
use App\Support\Messaging\Messenger;
use Carbon\CarbonInterface;

/**
 * Routes live-class notifications through the messaging hub (P2.4). The magic
 * join link is pre-built by the caller and passed straight into the template.
 */
final class MessengerSessionNotifier implements SessionNotifier
{
    public function __construct(private readonly Messenger $messenger) {}

    public function reminder(User $student, LiveSession $session, string $magicJoinUrl, string $window): void
    {
        $this->messenger->send($student, 'class_reminder', [
            'name' => $student->name,
            'title' => $session->title,
            'window' => $window,
            'link' => $magicJoinUrl,
        ]);
    }

    public function cancelled(User $student, LiveSession $session, string $reason): void
    {
        $this->messenger->send($student, 'class_cancelled', [
            'name' => $student->name,
            'title' => $session->title,
            'reason' => $reason,
        ]);
    }

    public function rescheduled(User $student, LiveSession $session, CarbonInterface $previousStart, string $reason): void
    {
        $this->messenger->send($student, 'class_rescheduled', [
            'name' => $student->name,
            'title' => $session->title,
            'reason' => $reason,
        ]);
    }

    public function trainerCancelled(User $trainer, LiveSession $session, string $reason): void
    {
        $this->messenger->send($trainer, 'class_cancelled_trainer', [
            'name' => $trainer->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'reason' => $reason,
        ]);
    }

    public function trainerRescheduled(User $trainer, LiveSession $session, CarbonInterface $previousStart, string $reason): void
    {
        $this->messenger->send($trainer, 'class_rescheduled_trainer', [
            'name' => $trainer->name,
            'title' => $session->title,
            'batch' => $this->batchLabel($session),
            'starts' => $session->scheduled_start->format('j M, g:i A'),
            'reason' => $reason,
        ]);
    }

    private function batchLabel(LiveSession $session): string
    {
        $batch = $session->batch;
        $course = $batch?->course?->name;

        return $course !== null ? "{$course} · Batch {$batch->number}" : 'your batch';
    }
}
