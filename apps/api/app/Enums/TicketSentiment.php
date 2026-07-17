<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the student sounds in a ticket (PRD §6.13 "urgency/sentiment detection — an angry
 * payment dispute jumps the queue"). Advisory: it is shown to staff and can raise a
 * ticket's priority, but it never routes, never resolves, and never replies.
 *
 * An unreadable signal is `Neutral`, not `Angry` — a fail-safe that under-reacts. The
 * cost of missing one angry ticket is a slow reply; the cost of crying wolf is a queue
 * staff stop trusting.
 */
enum TicketSentiment: string
{
    case Calm = 'calm';
    case Neutral = 'neutral';
    case Frustrated = 'frustrated';
    case Angry = 'angry';

    public static function fromLabel(?string $label): self
    {
        return self::tryFrom(strtolower(trim((string) $label))) ?? self::Neutral;
    }

    /** Frustrated and angry are what a human should see first. */
    public function isDistressed(): bool
    {
        return $this === self::Frustrated || $this === self::Angry;
    }
}
