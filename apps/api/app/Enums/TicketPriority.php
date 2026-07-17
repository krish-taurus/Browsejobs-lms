<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Queue order (PRD §6.13). Also what makes "jumps the queue" real rather than
     * decorative — the staff queue sorts on this, so raising a priority actually moves
     * a ticket. Note SLA due-times are per-category (`ticket_routes`) and are NOT
     * affected by priority: a jump changes who is seen first, not what is promised.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }

    public function isAbove(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public static function fromLabel(?string $label): self
    {
        return self::tryFrom(strtolower(trim((string) $label))) ?? self::Normal;
    }
}
