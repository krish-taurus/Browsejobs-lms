<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Manually reassigns a ticket to a staff member (PRD §6.13 "manual reassignment
 * allowed"). Audited and notified.
 */
final readonly class AssignTicket
{
    public function __construct(
        private RecordTicketTimeline $timeline,
        private NotifyTicket $notify,
        private AuditLogger $audit,
    ) {}

    public function handle(Ticket $ticket, User $assignee, ?User $actor = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $assignee, $actor): Ticket {
            $ticket->update(['assignee_id' => $assignee->id]);

            $this->audit->log('ticket.reassigned', $ticket, ['assignee_id' => $assignee->id], $actor);
            $this->notify->toStaff($ticket->fresh(), $assignee, 'ticket_assigned');
            $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_ASSIGNED, "Ticket {$ticket->reference} reassigned to {$assignee->name}", ['assignee_id' => $assignee->id], $actor);

            return $ticket->fresh();
        });
    }
}
