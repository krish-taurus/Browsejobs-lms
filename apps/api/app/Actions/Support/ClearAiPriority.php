<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Staff undo for an AI priority raise (PRD §6.13).
 *
 * The counterweight to `TriageTicket::maybeRaisePriority`: a model may move a ticket up
 * the queue, and a human may put it back in one click. Without this, an over-eager
 * triage would be something staff have to work around rather than correct, and a queue
 * staff work around is a queue they stop reading.
 *
 * Clearing the flag also means triage's raise is not re-applied — triage is idempotent
 * on `ai_triaged_at` and never runs twice.
 */
final readonly class ClearAiPriority
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Ticket $ticket, TicketPriority $to, ?User $actor = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $to, $actor): Ticket {
            if (! $ticket->ai_priority_raised) {
                return $ticket;
            }

            $from = $ticket->priority;
            $ticket->forceFill(['priority' => $to->value, 'ai_priority_raised' => false])->save();

            $this->audit->log('ticket.ai_priority_cleared', $ticket, [
                'from' => $from->value,
                'to' => $to->value,
            ], $actor);

            return $ticket->fresh();
        });
    }
}
