<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of a student's quiz attempt (PRD §6.5).
 */
enum QuizAttemptStatus: string
{
    case Pending = 'pending';         // dispatched, not yet opened
    case InProgress = 'in_progress';  // opened, timer running
    case Submitted = 'submitted';     // graded
    case Expired = 'expired';         // deadline passed before submit

    public function isTerminal(): bool
    {
        return $this === self::Submitted || $this === self::Expired;
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::InProgress;
    }
}
