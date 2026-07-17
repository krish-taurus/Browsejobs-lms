<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\TriageTicketJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On a new ticket (PRD §6.13): queue AI triage. Auto-discovered, like
 * NotifyTicketAssignee — the student's ticket is acknowledged and routed by rules
 * regardless of whether triage ever runs.
 */
final class TriageNewTicket implements ShouldQueue
{
    public function handle(TicketCreated $event): void
    {
        TriageTicketJob::dispatch($event->ticket->id);
    }
}
