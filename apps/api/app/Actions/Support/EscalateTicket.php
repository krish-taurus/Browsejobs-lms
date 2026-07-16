<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\ContactTimelineEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Escalates a ticket to an Admin (PRD §6.13 "auto-escalation to Admin on breach").
 * Reassigns to the first tenant admin, audits `ticket.escalated` (CLAUDE.md
 * required), and notifies. Idempotent — an already-escalated ticket is untouched.
 */
final readonly class EscalateTicket
{
    public function __construct(
        private RecordTicketTimeline $timeline,
        private NotifyTicket $notify,
        private AuditLogger $audit,
    ) {}

    public function handle(Ticket $ticket, string $reason, ?User $actor = null): Ticket
    {
        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $reason, $actor): Ticket {
            if ($ticket->escalated_at !== null) {
                return $ticket;
            }

            $admin = $this->firstAdmin($ticket->tenant_id);

            $ticket->update([
                'escalated_at' => now(),
                'escalated_to_id' => $admin?->id,
                'assignee_id' => $admin?->id ?? $ticket->assignee_id,
            ]);

            $this->audit->log('ticket.escalated', $ticket, ['reason' => $reason, 'admin_id' => $admin?->id], $actor);

            if ($admin !== null) {
                $this->notify->toStaff($ticket->fresh(), $admin, 'ticket_assigned');
            }

            $this->timeline->handle($ticket, ContactTimelineEvent::TYPE_NOTE, "Ticket {$ticket->reference} escalated: {$reason}", ['reason' => $reason], $actor);

            return $ticket->fresh();
        });
    }

    private function firstAdmin(?int $tenantId): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
            ->orderBy('id')
            ->first();
    }
}
