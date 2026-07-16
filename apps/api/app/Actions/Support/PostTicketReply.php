<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\TicketAuthorType;
use App\Enums\TicketStatus;
use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;

/**
 * Appends a reply to a ticket thread (PRD §6.13). A staff member's first public
 * reply satisfies the first-response SLA and moves the ticket to In Progress; a
 * student reply nudges a Waiting ticket back to In Progress. The counterpart is
 * notified (internal notes notify no one).
 */
final readonly class PostTicketReply
{
    public function __construct(
        private StoreTicketAttachments $attachments,
        private RecordTicketTimeline $timeline,
        private NotifyTicket $notify,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(
        Ticket $ticket,
        User $author,
        string $body,
        bool $isInternal = false,
        string $channel = 'portal',
        array $files = [],
    ): TicketMessage {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $author, $body, $isInternal, $channel, $files): TicketMessage {
            $type = $author->id === $ticket->student_id ? TicketAuthorType::Student : TicketAuthorType::Staff;

            $message = TicketMessage::query()->create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'author_id' => $author->id,
                'author_type' => $type->value,
                'body' => $body,
                'is_internal' => $isInternal,
                'channel' => $channel,
            ]);

            $this->attachments->handle($ticket, $message, $files);

            if ($type === TicketAuthorType::Staff && ! $isInternal) {
                $updates = ['status' => TicketStatus::InProgress->value];
                if ($ticket->first_response_at === null) {
                    $updates['first_response_at'] = now();
                }
                $ticket->update($updates);
                $this->notify->toStudent($ticket->fresh(), 'ticket_reply');
            } elseif ($type === TicketAuthorType::Student) {
                if ($ticket->status === TicketStatus::WaitingOnStudent) {
                    $ticket->update(['status' => TicketStatus::InProgress->value]);
                }
                $this->notify->toAssignee($ticket->fresh(), 'ticket_reply');
            }

            if (! $isInternal) {
                $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_NOTE, "Ticket {$ticket->reference}: new reply", ['author_type' => $type->value]);
            }

            return $message;
        });
    }
}
