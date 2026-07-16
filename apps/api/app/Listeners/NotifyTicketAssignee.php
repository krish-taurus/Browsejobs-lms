<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Support\NotifyTicket;
use App\Events\TicketCreated;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On a new ticket (PRD §6.13 "the moment a ticket lands…"): ack the student and
 * ping the assigned staff member (WhatsApp/email/dashboard via the Messenger).
 * Queued — never inline in the request cycle.
 */
final class NotifyTicketAssignee implements ShouldQueue
{
    public function __construct(private readonly NotifyTicket $notify) {}

    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $tenant = $ticket->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($ticket): void {
            $this->notify->toStudent($ticket, 'ticket_created_ack');
            $this->notify->toAssignee($ticket, 'ticket_assigned');
        });
    }
}
