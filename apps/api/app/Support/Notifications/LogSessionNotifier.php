<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default live-class notifier: writes to the log. Stands in for the real
 * WhatsApp/email/push channels until the P2.4 messaging hub.
 */
final class LogSessionNotifier implements SessionNotifier
{
    public function reminder(User $student, LiveSession $session, string $magicJoinUrl, string $window): void
    {
        Log::info('Class reminder', [
            'student_id' => $student->id,
            'session_id' => $session->id,
            'window' => $window,
            'join_url' => $magicJoinUrl,
        ]);
    }

    public function cancelled(User $student, LiveSession $session, string $reason): void
    {
        Log::info('Class cancelled', [
            'student_id' => $student->id,
            'session_id' => $session->id,
            'reason' => $reason,
        ]);
    }

    public function rescheduled(User $student, LiveSession $session, CarbonInterface $previousStart, string $reason): void
    {
        Log::info('Class rescheduled', [
            'student_id' => $student->id,
            'session_id' => $session->id,
            'old_start' => $previousStart->toIso8601String(),
            'new_start' => $session->scheduled_start->toIso8601String(),
            'reason' => $reason,
        ]);
    }
}
