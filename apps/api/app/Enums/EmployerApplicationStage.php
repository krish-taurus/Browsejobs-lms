<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application pipeline stages (PRD-E F3/F5).
 *
 * F3 ships the spine; F5 layers interview orchestration and automation
 * on top. Rejected is a parked-with-reason state a human chose;
 * automation may only park for review, never terminally reject (PRD-E
 * F6 guardrail — enforced in the automation engine, not here).
 */
enum EmployerApplicationStage: string
{
    case Applied = 'applied';
    case Graded = 'graded';
    case Shortlisted = 'shortlisted';
    case L1 = 'l1';
    case L2 = 'l2';
    case HumanRound = 'human_round';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    /** Forward order of the active pipeline (terminal states excluded). */
    public static function order(): array
    {
        return [
            self::Applied,
            self::Graded,
            self::Shortlisted,
            self::L1,
            self::L2,
            self::HumanRound,
            self::Offer,
            self::Hired,
        ];
    }

    public function canAdvanceTo(self $target): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        if ($target === self::Rejected || $target === self::Withdrawn) {
            return true;
        }

        $order = self::order();
        $from = array_search($this, $order, true);
        $to = array_search($target, $order, true);

        return $from !== false && $to !== false && $to > $from;
    }
}
