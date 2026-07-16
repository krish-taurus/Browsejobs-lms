<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Lead;

/**
 * Computes a lead's priority score ("who's hot" — PRD §6.12). P2.1 ships a
 * rule-based implementation; P3 swaps in an engagement-telemetry model
 * (masterclass/bootcamp attendance, quiz activity, link clicks) behind this
 * same interface.
 */
interface LeadScorer
{
    /** Returns a 0–100 score for the lead. */
    public function score(Lead $lead): int;
}
