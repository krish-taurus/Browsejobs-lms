<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Moves a ticket through its status flow (PRD §6.13): Open → In Progress → Waiting
 * on Student → Resolved → Closed. Resolving stamps `resolved_at` and asks the
 * student for a CSAT rating.
 */
final readonly class ChangeTicketStatus
{
    public function __construct(
        private RecordTicketTimeline $timeline,
        private NotifyTicket $notify,
    ) {}

    public function handle(Ticket $ticket, TicketStatus $to, ?User $actor = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $to, $actor): Ticket {
            $updates = ['status' => $to->value];

            if ($to === TicketStatus::Resolved && $ticket->resolved_at === null) {
                $updates['resolved_at'] = now();
            }
            if ($to === TicketStatus::Closed && $ticket->closed_at === null) {
                $updates['closed_at'] = now();
            }

            $ticket->update($updates);

            if ($to === TicketStatus::Resolved) {
                $this->notify->toStudent($ticket->fresh(), 'ticket_resolved');
            }

            $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_NOTE, "Ticket {$ticket->reference} → {$to->value}", ['status' => $to->value], $actor);

            return $ticket->fresh();
        });
    }
}
