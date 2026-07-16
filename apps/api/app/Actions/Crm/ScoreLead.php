<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\Lead;
use App\Services\Crm\LeadScorer;

/**
 * Recomputes and persists a lead's priority score via the bound LeadScorer.
 */
final readonly class ScoreLead
{
    public function __construct(private LeadScorer $scorer) {}

    public function handle(Lead $lead): Lead
    {
        $lead->score = $this->scorer->score($lead);
        $lead->save();

        return $lead;
    }
}
