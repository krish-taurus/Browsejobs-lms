<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Support\TriageTicket;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued AI triage for a new ticket (PRD §6.13; CLAUDE.md: every AI call is a job).
 * Idempotent via `tickets.ai_triaged_at`, so a retry never re-raises a priority.
 *
 * Takes an id rather than the model so a retry re-reads current state — a ticket a
 * human has picked up in the meantime must not be re-prioritised underneath them.
 */
final class TriageTicketJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $ticketId) {}

    public function handle(TriageTicket $triage): void
    {
        $ticket = Ticket::query()->withoutGlobalScopes()->find($this->ticketId);

        if ($ticket !== null) {
            $triage->handle($ticket);
        }
    }
}
