<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Delivers live-class notifications. The default log driver is replaced by
 * WhatsApp/email/push adapters in the P2.4 messaging hub without touching
 * callers. Every reminder carries a magic dashboard link (never a raw Zoom URL).
 */
interface SessionNotifier
{
    public function reminder(User $student, LiveSession $session, string $magicJoinUrl, string $window): void;

    public function cancelled(User $student, LiveSession $session, string $reason): void;

    public function rescheduled(User $student, LiveSession $session, CarbonInterface $previousStart, string $reason): void;

    /** Keep the trainer in the loop when their class is cancelled. */
    public function trainerCancelled(User $trainer, LiveSession $session, string $reason): void;

    /** Keep the trainer in the loop when their class is rescheduled. */
    public function trainerRescheduled(User $trainer, LiveSession $session, CarbonInterface $previousStart, string $reason): void;
}
