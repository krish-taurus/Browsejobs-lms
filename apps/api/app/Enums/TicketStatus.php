<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnStudent = 'waiting_on_student';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Statuses that still count as an active/open ticket for SLA + routing.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::InProgress, self::WaitingOnStudent];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
