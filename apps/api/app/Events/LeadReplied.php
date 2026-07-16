<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead replies inbound (WhatsApp). P2.5's nudge ladders listen to
 * auto-pause a running sequence (ADR 0006's deferred "auto-pause on reply").
 */
final class LeadReplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $body,
    ) {}
}
