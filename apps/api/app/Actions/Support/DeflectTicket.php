<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\AiPurpose;
use App\Enums\DeflectionOutcome;
use App\Enums\TicketCategory;
use App\Enums\TutorConfidence;
use App\Models\AiEvent;
use App\Models\KnowledgeChunk;
use App\Models\TicketDeflection;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use App\Support\Tutor\KnowledgeRetriever;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The AI first-response / deflection layer (PRD §6.13): before a ticket is created,
 * answer from the tenant's `support` policy corpus plus the student's own account
 * data ("Your next EMI is ₹15,000 due 5 Aug"). Typically deflects 30–50% of volume.
 *
 * Runs synchronously — this is interactive, like the AI Tutor, and is the documented
 * exception to CLAUDE.md's queue-every-AI-call rule. The gateway enforces the
 * student's own daily token budget and logs the call to `ai_events`.
 *
 * **Deflection is assistive and never a gate.** Every failure path — feature off, no
 * grounding retrieved, budget exhausted, transport error, empty or low-confidence
 * answer — returns `null`, meaning "no answer, let the ticket through". A support desk
 * that swallows tickets when the model is down is worse than one with no AI at all.
 *
 * This action never creates, blocks, or modifies a ticket. It only offers an answer;
 * `CreateTicket` remains the single path a ticket is born through.
 *
 * Complements the tutor rather than overlapping it: `tutor.v2.md` is explicitly told to
 * refuse fees/refunds/deadlines/account questions and send them to a human — this is
 * the layer that answers them.
 */
final readonly class DeflectTicket
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private AiGateway $gateway,
        private TicketStudentContext $context,
    ) {}

    /**
     * @return TicketDeflection|null null → offer nothing and let the student raise a ticket
     */
    public function handle(User $student, TicketCategory $category, string $question): ?TicketDeflection
    {
        if (! config('support.ai.enabled', true)) {
            return null;
        }

        return app(TenantContext::class)->run($student->tenant, function () use ($student, $category, $question): ?TicketDeflection {
            $terms = $this->retriever->tokenize($question);

            // Scope to this tenant's support policy corpus for this category — a payments
            // question must never be "answered" out of course material.
            $chunks = $this->retriever->retrieve(
                $terms,
                (int) config('support.ai.retrieve_k', 4),
                null,
                ['support'],
                $category->value,
            );

            if ($chunks->isEmpty()) {
                return null; // nothing to ground on — a human takes it
            }

            try {
                $result = $this->gateway->complete(
                    $student,
                    AiPurpose::SupportDeflection,
                    'support_deflection',
                    (int) config('support.ai.prompt_version', 1),
                    [
                        'context' => $this->buildContext($chunks),
                        'student' => $this->buildStudentBlock($student),
                        'category' => $category->value,
                        'question' => $question,
                    ],
                    ['max_tokens' => (int) config('support.ai.max_answer_tokens', 500)],
                );
            } catch (Throwable $e) {
                // Budget exhausted or transport down. Reported so a broken deflection
                // layer is visible, swallowed so the student is never blocked.
                report($e);

                return null;
            }

            [$answer, $confidence] = TutorConfidence::split($result->text);

            if ($answer === '' || ! $confidence->meets($this->floor())) {
                return null;
            }

            return TicketDeflection::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'category' => $category->value,
                'question' => $question,
                'answer' => $answer,
                'confidence' => $confidence->value,
                'cited_chunk_ids' => $chunks->pluck('id')->all(),
                'outcome' => DeflectionOutcome::Offered->value,
                'ai_event_id' => $this->lastEventId($student),
            ]);
        });
    }

    /** The configured confidence floor for showing an answer at all. */
    private function floor(): TutorConfidence
    {
        return TutorConfidence::fromLabel((string) config('support.ai.min_confidence', 'medium'));
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    private function buildContext(Collection $chunks): string
    {
        $blocks = [];
        foreach ($chunks->values() as $i => $chunk) {
            $source = $chunk->document?->title ?? 'Support policy';
            $blocks[] = '[C'.($i + 1)."] ({$source})\n".trim($chunk->content);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * The student's own account facts, verbatim — the prompt forbids the model from
     * estimating money or dates, so anything it may state about the account is stated
     * here first. Reuses the staff sidebar payload rather than assembling a second one.
     */
    private function buildStudentBlock(User $student): string
    {
        $ctx = $this->context->handle($student);

        $lines = ['Name: '.$student->name];

        $lines[] = $ctx['batch'] === null
            ? 'Batch: not enrolled in an active batch'
            : 'Batch: '.$ctx['batch']['number'].' ('.($ctx['batch']['course'] ?? 'course').')';

        $lines[] = 'Total outstanding fees: '.$this->rupees((int) $ctx['fee']['outstanding_paise']);

        $next = $ctx['fee']['next_due'] ?? null;
        $lines[] = $next === null
            ? 'Next instalment: none due'
            : 'Next instalment: '.$this->rupees((int) $next['amount_paise']).' due '.$next['due_on'];

        if ($ctx['fee']['blocked'] === true) {
            $lines[] = 'Account access: currently blocked for unpaid fees';
        }

        $lines[] = 'Attendance: '.$ctx['attendance_pct'].'%';

        return implode("\n", $lines);
    }

    /** Paise → a rupee string. Integer math only (CLAUDE.md); paise are never floats. */
    private function rupees(int $paise): string
    {
        $whole = number_format(intdiv($paise, 100));
        $fraction = $paise % 100;

        return $fraction === 0 ? '₹'.$whole : '₹'.$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The event the gateway just logged. Mirrors `ReportNarrator` — the gateway does not
     * hand the row back, and widening its return type is out of this slice's scope.
     */
    private function lastEventId(User $student): ?int
    {
        $id = AiEvent::query()
            ->where('user_id', $student->id)
            ->where('purpose', AiPurpose::SupportDeflection->value)
            ->latest('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
