<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Messaging\Messenger;

/**
 * Ticket notifications (PRD §6.13 "the moment a ticket lands, the assigned staff
 * member is pinged"). Wraps the Messenger with common ticket vars + the right
 * deep link. No-ops gracefully when a template is missing.
 */
final readonly class NotifyTicket
{
    public function __construct(private Messenger $messenger) {}

    public function toStudent(Ticket $ticket, string $key): void
    {
        $this->send($ticket, $ticket->student, $key);
    }

    public function toAssignee(Ticket $ticket, string $key): void
    {
        if ($ticket->assignee !== null) {
            $this->send($ticket, $ticket->assignee, $key);
        }
    }

    public function toStaff(Ticket $ticket, User $staff, string $key): void
    {
        $this->send($ticket, $staff, $key);
    }

    private function send(Ticket $ticket, ?User $recipient, string $key): void
    {
        if ($recipient === null) {
            return;
        }

        $isStudent = $recipient->id === $ticket->student_id;
        $path = $isStudent ? "/support/{$ticket->id}" : "/admin/support/{$ticket->id}";

        $this->messenger->send($recipient, $key, [
            'name' => $recipient->name,
            'ref' => $ticket->reference,
            'subject' => $ticket->subject,
            'link' => rtrim((string) config('app.frontend_url', ''), '/').$path,
        ]);
    }
}
