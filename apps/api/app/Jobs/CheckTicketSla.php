<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Support\EscalateTicket;
use App\Actions\Support\NotifyTicket;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Support-ticket SLA enforcer (PRD §6.13). Dispatched with a delay for each phase:
 * `warning` (75% of first-response → ping assignee), `first_response` (breach →
 * escalate if unanswered), `resolution` (breach → escalate if unresolved). Each
 * phase is idempotent and self-guards against the sync driver ignoring the delay.
 */
final class CheckTicketSla implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $phase,
    ) {}

    public function handle(NotifyTicket $notify, EscalateTicket $escalate): void
    {
        $ticket = Ticket::query()->withoutGlobalScopes()->find($this->ticketId);
        if ($ticket === null || ! $ticket->status->isActive()) {
            return;
        }

        $tenant = Tenant::query()->find($ticket->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($ticket, $notify, $escalate): void {
            match ($this->phase) {
                'warning' => $this->warn($ticket, $notify),
                'first_response' => $this->firstResponse($ticket, $escalate),
                'resolution' => $this->resolution($ticket, $escalate),
                default => null,
            };
        });
    }

    private function warn(Ticket $ticket, NotifyTicket $notify): void
    {
        if ($ticket->first_response_at !== null
            || $ticket->response_warned_at !== null
            || $ticket->first_response_due_at === null
            || now()->lt($this->warnThreshold($ticket))) {
            return;
        }

        $ticket->update(['response_warned_at' => now()]);
        $notify->toAssignee($ticket->fresh(), 'ticket_sla_warning');
    }

    private function firstResponse(Ticket $ticket, EscalateTicket $escalate): void
    {
        if ($ticket->first_response_at !== null
            || $ticket->breached_at !== null
            || $ticket->first_response_due_at === null
            || $ticket->first_response_due_at->isFuture()) {
            return;
        }

        $ticket->update(['breached_at' => now()]);
        $escalate->handle($ticket->fresh(), 'First-response SLA breached');
    }

    private function resolution(Ticket $ticket, EscalateTicket $escalate): void
    {
        if ($ticket->resolution_breached_at !== null
            || $ticket->resolution_due_at === null
            || $ticket->resolution_due_at->isFuture()) {
            return;
        }

        $ticket->update(['resolution_breached_at' => now()]);
        $escalate->handle($ticket->fresh(), 'Resolution SLA breached');
    }

    /** The 75%-of-first-response mark, derived from the ticket window. */
    private function warnThreshold(Ticket $ticket): Carbon
    {
        $windowSeconds = $ticket->created_at->diffInSeconds($ticket->first_response_due_at);
        $remainingPct = 1 - (float) config('support.sla_warning_pct', 0.75);

        return $ticket->first_response_due_at->copy()->subSeconds((int) round($windowSeconds * $remainingPct));
    }
}
