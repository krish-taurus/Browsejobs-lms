<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Crm\RecordTimelineEvent;
use App\Models\Ticket;
use App\Models\User;

/**
 * Records a ticket event on the student's CRM contact timeline (PRD §6.13 "every
 * ticket event lands on the student's CRM contact timeline"). No-op when the
 * ticket has no correlated lead.
 */
final readonly class RecordTicketTimeline
{
    public function __construct(private RecordTimelineEvent $timeline) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(Ticket $ticket, string $type, ?string $body = null, ?array $meta = null, ?User $actor = null): void
    {
        $lead = $ticket->lead;
        if ($lead === null) {
            return;
        }

        $this->timeline->handle(
            $lead,
            $type,
            $body,
            array_merge(['ticket_id' => $ticket->id, 'ticket_ref' => $ticket->reference], $meta ?? []),
            $actor,
        );
    }
}
