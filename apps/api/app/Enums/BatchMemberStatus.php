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
