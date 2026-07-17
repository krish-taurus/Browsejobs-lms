<?php

declare(strict_types=1);

namespace App\Enums;

enum BatchMemberStatus: string
{
    case Reserved = 'reserved';
    case PaymentPending = 'payment_pending';
    case Enrolled = 'enrolled';
    case Dropped = 'dropped';
    case Transferred = 'transferred';
    case Completed = 'completed';
    // Frozen enrolment (PRD §6.20 pause/defer) — rejoins a later batch.
    // Not occupying: the seat frees up for the running batch.
    case Paused = 'paused';

    /**
     * Statuses that occupy a seat for capacity purposes.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Reserved, self::PaymentPending, self::Enrolled, self::Completed];
    }
}
