<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a support ticket is raised (PRD §6.13; named in CLAUDE.md). The
 * NotifyTicketAssignee listener acks the student + pings the assignee.
 */
final class TicketCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}
}
