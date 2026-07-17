<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\DeflectionOutcome;
use App\Models\Ticket;
use App\Models\TicketDeflection;

/**
 * Closes the loop on an offered AI first-response (PRD §6.13): the student either
 * accepted the answer (no ticket) or proceeded anyway (ticket raised). The accept rate
 * across these rows is the deflection rate — measured, not assumed.
 *
 * Idempotent: an already-resolved deflection keeps its first outcome, so a double-tap
 * or a retried request cannot inflate the accept rate.
 */
final readonly class ResolveDeflection
{
    public function accepted(TicketDeflection $deflection): TicketDeflection
    {
        return $this->resolve($deflection, DeflectionOutcome::Accepted, null);
    }

    /** The answer did not land — record the ticket it failed to prevent. */
    public function proceeded(TicketDeflection $deflection, Ticket $ticket): TicketDeflection
    {
        return $this->resolve($deflection, DeflectionOutcome::Proceeded, $ticket);
    }

    private function resolve(TicketDeflection $deflection, DeflectionOutcome $outcome, ?Ticket $ticket): TicketDeflection
    {
        if ($deflection->outcome !== DeflectionOutcome::Offered) {
            return $deflection;
        }

        $deflection->forceFill(array_filter([
            'outcome' => $outcome->value,
            'ticket_id' => $ticket?->id,
            'resolved_at' => now(),
        ], fn ($v) => $v !== null))->save();

        return $deflection;
    }
}
