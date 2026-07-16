<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead enters the CRM (any capture source). First-class per PRD
 * §6.12; P2.4 messaging (WhatsApp confirmation) and future automations listen.
 */
final class LeadCaptured
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}
}
