<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Jobs\CheckTicketSla;
use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Models\TicketRoute;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Reopens a resolved/closed ticket within the reopen window (PRD §6.13 "reopen
 * within 7 days"). Moves it back to In Progress and re-arms the resolution SLA.
 */
final readonly class ReopenTicket
{
    public function __construct(private RecordTicketTimeline $timeline) {}

    public function handle(Ticket $ticket, ?User $actor = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $actor): Ticket {
            if (! $ticket->reopenable()) {
                throw ValidationException::withMessages(['ticket' => 'This ticket can no longer be reopened.']);
            }

            $resMin = TicketRoute::query()->where('category', $ticket->category->value)->value('resolution_minutes') ?? 2880;

            $ticket->update([
                'status' => TicketStatus::InProgress->value,
                'reopened_at' => now(),
                'resolved_at' => null,
                'closed_at' => null,
                'resolution_due_at' => now()->addMinutes((int) $resMin),
                'resolution_breached_at' => null,
            ]);

            CheckTicketSla::dispatch($ticket->id, 'resolution')->delay($ticket->fresh()->resolution_due_at);

            $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_NOTE, "Ticket {$ticket->reference} reopened", [], $actor);

            return $ticket->fresh();
        });
    }
}
