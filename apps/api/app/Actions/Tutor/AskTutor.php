<?php

declare(strict_types=1);

namespace App\Actions\Tutor;

use App\Actions\Support\CreateTicket;
use App\Enums\AiPurpose;
use App\Enums\TicketCategory;
use App\Enums\TutorConfidence;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\KnowledgeChunk;
use App\Models\Ticket;
use App\Models\TutorConversation;
use App\Models\TutorMessage;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use App\Support\Tutor\KnowledgeRetriever;
use Illuminate\Support\Collection;

/**
 * The AI Tutor answer pipeline (PRD §6.4): retrieve course context → ground the model
 * → persist a cited answer → escalate to the trainer on low confidence or a repeated
 * question. Runs synchronously (interactive chat, like the coding labs). The gateway
 * enforces the per-student token budget and logs the call.
 */
final readonly class AskTutor
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private AiGateway $gateway,
        private CreateTicket $createTicket,
    ) {}

    public function handle(User $student, string $question, TutorConversation $conversation, ?CodingLab $lab = null): TutorMessage
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $question, $conversation, $lab): TutorMessage {
            $fingerprint = $this->fingerprint($question);

            TutorMessage::query()->create([
                'tenant_id' => $student->tenant_id,
                'conversation_id' => $conversation->id,
                'student_id' => $student->id,
                'role' => TutorMessage::ROLE_STUDENT,
                'body' => $question,
                'question_fingerprint' => $fingerprint,
            ]);

            $terms = $this->retriever->tokenize($question);
            $chunks = $this->retriever->retrieve($terms, (int) config('ai.tutor.retrieve_k', 5), $lab?->lesson_id);

            $context = $this->buildContext($chunks, $lab, $student);

            $result = $this->gateway->complete(
                $student,
                AiPurpose::Tutor,
                'tutor',
                (int) config('ai.tutor.prompt_version', 2),
                ['context' => $context, 'question' => $question],
                ['max_tokens' => (int) config('ai.tutor.max_answer_tokens', 700)],
            );

            [$answer, $confidence] = TutorConfidence::split($result->text);

            $repeat = $this->isRepeat($student, $fingerprint);
            $escalate = $confidence->shouldEscalate() || $repeat;

            $ticketId = null;
            if ($escalate) {
                $ticketId = $this->escalate($student, $question, $answer, $repeat)->id;
            }

            $message = TutorMessage::query()->create([
                'tenant_id' => $student->tenant_id,
                'conversation_id' => $conversation->id,
                'student_id' => $student->id,
                'role' => TutorMessage::ROLE_TUTOR,
                'body' => $answer,
                'cited_chunk_ids' => $chunks->pluck('id')->all(),
                'confidence' => $confidence->value,
                'escalated' => $escalate,
                'ticket_id' => $ticketId,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });
    }

    /** A normalized token-set hash, so re-asking the same thing collides. */
    private function fingerprint(string $question): string
    {
        $terms = $this->retriever->tokenize($question);
        sort($terms);

        return hash('sha256', implode(' ', $terms));
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    private function buildContext(Collection $chunks, ?CodingLab $lab, User $student): string
    {
        $blocks = [];
        foreach ($chunks->values() as $i => $chunk) {
            $tag = '[C'.($i + 1).']';
            $source = $chunk->document?->title ?? 'Course material';
            $blocks[] = "{$tag} ({$source})\n".trim($chunk->content);
        }

        if ($chunks->isEmpty()) {
            $blocks[] = '(No matching course material was found.)';
        }

        if ($lab !== null) {
            // Hint-ladder context: lab brief + the student's own last run — NEVER the
            // hidden test cases / expected outputs.
            $blocks[] = "[LAB] The student is working on a coding lab.\nInstructions:\n".trim((string) $lab->instructions);

            $submission = CodeSubmission::query()
                ->where('user_id', $student->id)
                ->where('lesson_id', $lab->lesson_id)
                ->latest('id')
                ->first();

            if ($submission !== null) {
                $blocks[] = "[LAB] The student's latest attempt output:\nstdout: ".trim((string) $submission->stdout)
                    ."\nstderr: ".trim((string) $submission->stderr);
            }
        }

        return implode("\n\n", $blocks);
    }

    private function isRepeat(User $student, string $fingerprint): bool
    {
        $window = now()->subHours((int) config('ai.tutor.repeat_window_hours', 24));

        $priorAsks = TutorMessage::query()
            ->where('student_id', $student->id)
            ->where('role', TutorMessage::ROLE_STUDENT)
            ->where('question_fingerprint', $fingerprint)
            ->where('created_at', '>=', $window)
            ->count();

        // The current ask is already persisted, so >= threshold means it recurred.
        return $priorAsks >= (int) config('ai.tutor.repeat_threshold', 3);
    }

    private function escalate(User $student, string $question, string $answer, bool $repeat): Ticket
    {
        $reason = $repeat
            ? 'A student has asked the AI Tutor the same question repeatedly without resolution.'
            : 'The AI Tutor was not confident it could answer this from the course material.';

        $body = "{$reason}\n\nStudent's question:\n{$question}\n\nTutor's best attempt:\n{$answer}";

        return $this->createTicket->handle(
            $student,
            TicketCategory::Academic,
            'AI Tutor escalation: '.str($question)->limit(60)->toString(),
            $body,
        );
    }
}
