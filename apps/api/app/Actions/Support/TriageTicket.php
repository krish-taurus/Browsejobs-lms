<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\AiPurpose;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketStatus;
use App\Models\AiEvent;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Throwable;

/**
 * AI triage for a new ticket (PRD §6.13): category suggestion, urgency, sentiment, and
 * duplicate detection — four signals from one call.
 *
 * **AI signals sit beside the human ones, never on top of them.** `category` and
 * routing stay rule-owned: the PRD asks for a category *suggestion*, and re-routing a
 * ticket between teams on a model's say-so would move work with nobody accountable. The
 * suggestion lands in `ai_category` for staff to act on.
 *
 * `priority` is the single field triage may write, because "an angry payment dispute
 * jumps the queue" is only true if something actually moves. It is fenced:
 *  - raise only, never lower;
 *  - only on first triage of a ticket no staff member has touched;
 *  - `ai_priority_raised` is set so the queue can say a model did this, and staff can
 *    undo it in one click (`ClearAiPriority`);
 *  - audited, so a silent reshuffle of the queue is impossible.
 *
 * Priority does not move SLA due-times — those are per-category from `ticket_routes`.
 * A jump changes who is seen first, not what was promised.
 *
 * Billed to staff (assignee, else an admin), never the student: this is our
 * bookkeeping, not their question, and their budget is for the tutor and deflection.
 * Fail-safe throughout — a model that is down, over budget, or talking nonsense leaves
 * a perfectly ordinary ticket for a human, exactly as before P3.5d-b existed.
 */
final readonly class TriageTicket
{
    public function __construct(
        private AiGateway $gateway,
        private AuditLogger $audit,
    ) {}

    public function handle(Ticket $ticket): ?Ticket
    {
        if (! config('support.ai.triage_enabled', true)) {
            return null;
        }

        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket): ?Ticket {
            if ($ticket->ai_triaged_at !== null) {
                return $ticket; // idempotent — a retried job never re-raises a priority
            }

            $billTo = $this->billingUser($ticket);
            if ($billTo === null) {
                return null; // nobody to bill: skip rather than charge a student for our triage
            }

            $duplicateCandidates = $this->openTickets($ticket);

            try {
                $result = $this->gateway->complete(
                    $billTo,
                    AiPurpose::SupportTriage,
                    'support_triage',
                    (int) config('support.ai.triage_prompt_version', 1),
                    [
                        'chosen_category' => $ticket->category->value,
                        'subject' => $ticket->subject,
                        'body' => $this->firstMessage($ticket),
                        'open_tickets' => $this->renderCandidates($duplicateCandidates),
                    ],
                    ['max_tokens' => (int) config('support.ai.triage_max_tokens', 200)],
                );
            } catch (Throwable $e) {
                report($e);

                return null;
            }

            $parsed = JsonOutput::object($result->text);
            if ($parsed === null) {
                return null; // unparseable → an ordinary untriaged ticket
            }

            $category = TicketCategory::tryFrom(JsonOutput::string($parsed, 'category'));
            $urgency = TicketPriority::fromLabel(JsonOutput::string($parsed, 'urgency'));
            $sentiment = TicketSentiment::fromLabel(JsonOutput::string($parsed, 'sentiment'));

            $ticket->forceFill([
                'ai_category' => $category?->value,
                'ai_urgency' => $urgency->value,
                'ai_sentiment' => $sentiment->value,
                'ai_duplicate_of_id' => $this->resolveDuplicate($parsed, $duplicateCandidates),
                'ai_triaged_at' => now(),
                'ai_event_id' => $this->lastEventId($billTo),
            ])->save();

            $this->maybeRaisePriority($ticket, $urgency, $sentiment);

            return $ticket->fresh();
        });
    }

    /**
     * The PRD's "an angry payment dispute jumps the queue": an angry student is worth at
     * least `high` even when the model rated the request itself routine.
     */
    private function maybeRaisePriority(Ticket $ticket, TicketPriority $urgency, TicketSentiment $sentiment): void
    {
        $target = $urgency;
        if ($sentiment === TicketSentiment::Angry && ! $target->isAbove(TicketPriority::Normal)) {
            $target = TicketPriority::High;
        }

        if (! $target->isAbove($ticket->priority) || ! $this->untouched($ticket)) {
            return;
        }

        $from = $ticket->priority;
        $ticket->forceFill(['priority' => $target->value, 'ai_priority_raised' => true])->save();

        $this->audit->log('ticket.priority_raised_by_ai', $ticket, [
            'from' => $from->value,
            'to' => $target->value,
            'urgency' => $urgency->value,
            'sentiment' => $sentiment->value,
        ]);
    }

    /** Nobody has worked this ticket yet, so raising its priority overrides no human. */
    private function untouched(Ticket $ticket): bool
    {
        return $ticket->status === TicketStatus::Open
            && ! $ticket->messages()->where('author_type', 'staff')->exists();
    }

    /**
     * The model may only name an id we showed it — it cannot invent a ticket, point at
     * another student's, or cross a tenant.
     *
     * @param  Collection<int, Ticket>  $candidates
     */
    private function resolveDuplicate(array $parsed, $candidates): ?int
    {
        $id = JsonOutput::positiveInt($parsed, 'duplicate_of');

        return $id !== null && $candidates->contains('id', $id) ? $id : null;
    }

    /** @return Collection<int, Ticket> */
    private function openTickets(Ticket $ticket)
    {
        return Ticket::query()
            ->where('student_id', $ticket->student_id)
            ->where('id', '!=', $ticket->id)
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::WaitingOnStudent->value])
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'subject', 'category']);
    }

    /** @param Collection<int, Ticket> $candidates */
    private function renderCandidates($candidates): string
    {
        if ($candidates->isEmpty()) {
            return '(none)';
        }

        return $candidates
            ->map(fn (Ticket $t) => "id {$t->id} [{$t->category->value}] {$t->subject}")
            ->implode("\n");
    }

    private function firstMessage(Ticket $ticket): string
    {
        return (string) $ticket->messages()->orderBy('id')->value('body');
    }

    /** Assignee, else a tenant admin. Never the student. */
    private function billingUser(Ticket $ticket): ?User
    {
        if ($ticket->assignee_id !== null) {
            $assignee = User::query()->find($ticket->assignee_id);
            if ($assignee !== null) {
                return $assignee;
            }
        }

        return User::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
            ->orderBy('id')
            ->first();
    }

    private function lastEventId(User $billTo): ?int
    {
        $id = AiEvent::query()
            ->where('user_id', $billTo->id)
            ->where('purpose', AiPurpose::SupportTriage->value)
            ->latest('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
