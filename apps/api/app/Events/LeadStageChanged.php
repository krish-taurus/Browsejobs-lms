<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lead;
use App\Models\LeadStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead moves between pipeline stages (PRD §6.12). P2.2 payments and
 * reporting listen for stage velocity / conversion.
 */
final class LeadStageChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?LeadStage $from,
        public readonly LeadStage $to,
    ) {}
}
