<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead is assigned (or reassigned) to a counselor (PRD §6.12).
 */
final class LeadAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly User $counselor,
        public readonly bool $manual,
    ) {}
}
