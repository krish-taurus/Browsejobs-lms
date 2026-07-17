<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\AiPurpose;
use App\Models\KnowledgeChunk;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use App\Support\Tutor\KnowledgeRetriever;
use Illuminate\Support\Collection;
use Throwable;

/**
 * AI-drafted reply suggestion for the staff workspace (PRD §6.13).
 *
 * **No approval flow, deliberately.** PRD §7 gates AI output that reaches a student
 * unreviewed; this never reaches anyone. It writes into the staff member's reply box,
 * they edit it, and `PostTicketReply` sends it under their name — the staff member *is*
 * the human in the loop, and adding a second approver would mean approving a draft that
 * a human has to approve again by pressing Send.
 *
 * Grounded in the same `support` corpus as deflection, so a draft cites the same policy
 * the student would have been shown. Synchronous: staff click a button and wait, like
 * the tutor. Billed to the staff member who asked.
 *
 * Returns null on any failure — the canned-response picker and the empty box are both
 * still there, which is exactly the workspace P2.7 shipped.
 */
final readonly class DraftTicketReply
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private AiGateway $gateway,
        private TicketStudentContext $context,
    ) {}

    public function handle(Ticket $ticket, User $staff): ?string
    {
        if (! config('support.ai.reply_drafts_enabled', true)) {
            return null;
        }

        return app(TenantContext::class)->run($ticket->tenant, function () use ($ticket, $staff): ?string {
            $ticket->loadMissing(['messages' => fn ($q) => $q->orderBy('id'), 'student']);

            if ($ticket->student === null) {
                return null;
            }

            $terms = $this->retriever->tokenize($ticket->subject.' '.$this->studentWords($ticket));
            $chunks = $this->retriever->retrieve(
                $terms,
                (int) config('support.ai.retrieve_k', 4),
                null,
                ['support'],
                $ticket->category->value,
            );

            try {
                $result = $this->gateway->complete(
                    $staff,
                    AiPurpose::SupportReply,
                    'support_reply',
                    (int) config('support.ai.reply_prompt_version', 1),
                    [
                        'context' => $this->buildContext($chunks),
                        'student' => $this->studentBlock($ticket->student),
                        'thread' => $this->buildThread($ticket),
                    ],
                    ['max_tokens' => (int) config('support.ai.reply_max_tokens', 400)],
                );
            } catch (Throwable $e) {
                report($e);

                return null;
            }

            $draft = trim($result->text);

            return $draft === '' ? null : $draft;
        });
    }

    /** Only what the student wrote — the draft answers them, not our own internal notes. */
    private function studentWords(Ticket $ticket): string
    {
        return $ticket->messages
            ->where('author_type', 'student')
            ->pluck('body')
            ->implode(' ');
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    private function buildContext(Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return '(No policy document covers this. Do not invent one.)';
        }

        $blocks = [];
        foreach ($chunks->values() as $i => $chunk) {
            $source = $chunk->document?->title ?? 'Support policy';
            $blocks[] = '[C'.($i + 1)."] ({$source})\n".trim($chunk->content);
        }

        return implode("\n\n", $blocks);
    }

    /** Internal notes are included: they are context the staff member already has. */
    private function buildThread(Ticket $ticket): string
    {
        return $ticket->messages
            ->map(function (TicketMessage $m): string {
                $who = match ($m->author_type->value ?? (string) $m->author_type) {
                    'student' => 'Student',
                    'staff' => $m->is_internal ? 'Staff (internal note)' : 'Staff',
                    default => 'System',
                };

                return "{$who}: ".trim($m->body);
            })
            ->implode("\n\n");
    }

    private function studentBlock(User $student): string
    {
        $ctx = $this->context->handle($student);

        $lines = ['Name: '.$student->name];
        $lines[] = $ctx['batch'] === null
            ? 'Batch: not enrolled in an active batch'
            : 'Batch: '.$ctx['batch']['number'].' ('.($ctx['batch']['course'] ?? 'course').')';

        $next = $ctx['fee']['next_due'] ?? null;
        $lines[] = $next === null
            ? 'Next instalment: none due'
            : 'Next instalment: ₹'.number_format(intdiv((int) $next['amount_paise'], 100)).' due '.$next['due_on'];

        $lines[] = 'Attendance: '.$ctx['attendance_pct'].'%';

        return implode("\n", $lines);
    }
}
