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
}
