<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketStatus;
use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Records the student's CSAT rating on a resolved/closed ticket (PRD §6.13 "CSAT
 * rating on resolution").
 */
final readonly class SubmitCsat
{
    public function __construct(private RecordTicketTimeline $timeline) {}

    public function handle(Ticket $ticket, int $rating, ?string $comment = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $rating, $comment): Ticket {
            if (! in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                throw ValidationException::withMessages(['ticket' => 'Only a resolved ticket can be rated.']);
            }

            $ticket->update(['csat_rating' => $rating, 'csat_comment' => $comment, 'csat_at' => now()]);

            $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_NOTE, "Ticket {$ticket->reference} rated {$rating}/5", ['csat' => $rating]);

            return $ticket->fresh();
        });
    }
}
