<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\AiPurpose;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\RealInterviewQuestion;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a candidate answer and, while questions remain, asks the adaptive
 * next question (PRD §6.6: adaptive follow-ups — probe weak answers, advance
 * past strong ones). Once the question cap is reached the session is ready
 * for the scorecard instead of burning more tokens.
 */
final readonly class AnswerMockInterview
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @return array{turn: MockTurn|null, ready_to_finish: bool}
     */
    public function handle(MockInterview $interview, string $answer): array
    {
        if ($interview->status !== MockInterview::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['mock' => 'This interview is already completed.']);
        }

        // One transaction for answer + follow-up: if the interviewer can't be
        // reached (budget/transport), the answer rolls back too, so a retry
        // never stores duplicates.
        return DB::transaction(fn (): array => $this->exchange($interview, $answer));
    }

    /**
     * @return array{turn: MockTurn|null, ready_to_finish: bool}
     */
    private function exchange(MockInterview $interview, string $answer): array
    {
        MockTurn::query()->create([
            'tenant_id' => $interview->tenant_id,
            'mock_interview_id' => $interview->id,
            'role' => MockTurn::ROLE_CANDIDATE,
            'body' => $answer,
        ]);

        $asked = $interview->interviewerQuestions();
        $blueprint = $interview->blueprint;
        // A blueprint may run longer than the platform default (the AI
        // Readiness Interview asks 15) — its own max_questions overrides,
        // same config every other mock kind still falls back to.
        $max = (int) ($blueprint->max_questions ?? config('mocks.max_questions', 6));

        if ($asked >= $max) {
            return ['turn' => null, 'ready_to_finish' => true];
        }

        $transcript = $this->transcript($interview);

        // v3 prompt (Sept 2026): v2 relied on the model inferring "don't
        // repeat yourself" from the transcript alone — on a long interview
        // (question 10+ of 15) it sometimes lost track and re-asked an
        // earlier question verbatim. v3 adds an explicit rule to re-check
        // every INTERVIEWER line before asking. The bank-question dedup
        // below is unrelated: it only stops the same BANK question being
        // suggested twice, not the model repeating a question of its own.
        $result = $this->gateway->complete($interview->student, AiPurpose::Mock, 'mock_interview', 3, [
            'role_title' => $blueprint->role_title,
            'competencies' => implode(', ', $blueprint->competencies),
            'remaining' => (string) max(0, $max - $asked - 1),
            'bank_questions' => $this->bankQuestions($interview, $transcript),
            'transcript' => $transcript,
            // Only the AI Readiness Interview (blueprint->user_id set) gets an
            // explicit difficulty ramp — every other mock kind gets an empty
            // string here, so its prompt behaviour is unchanged.
            'difficulty_guidance' => $blueprint->user_id !== null
                ? $this->difficultyGuidance($asked + 1, $max)
                : '',
        ], ['max_tokens' => 300]);

        $question = trim($result->text);
        if ($question === '') {
            throw ValidationException::withMessages(['mock' => 'The interviewer is unavailable right now — try again.']);
        }

        $turn = MockTurn::query()->create([
            'tenant_id' => $interview->tenant_id,
            'mock_interview_id' => $interview->id,
            'role' => MockTurn::ROLE_INTERVIEWER,
            'body' => $question,
        ]);

        return ['turn' => $turn, 'ready_to_finish' => $asked + 1 >= $max];
    }

    /**
     * A general readiness interview has no experience band to key
     * difficulty off (unlike a JD's own experience_min/max_years) — so it
     * ramps by position instead: fundamentals early, applied scenarios
     * through the middle, advanced judgement calls toward the end.
     */
    private function difficultyGuidance(int $questionNumber, int $max): string
    {
        $progress = $questionNumber / $max;
        $band = match (true) {
            $progress <= 1 / 3 => 'a FUNDAMENTALS question — basic concept checks, definitions, "what is" / "how does X work"',
            $progress <= 2 / 3 => 'an APPLIED question — a realistic scenario that needs the concept put to use, not just defined',
            default => 'an ADVANCED question — trade-offs, edge cases, or a judgement call an experienced person would face',
        };

        return "- This is a {$max}-question general readiness interview built from the candidate's own CV, deliberately ramping in difficulty. ".
            "You are on question {$questionNumber} of {$max} — make this {$band}.";
    }

    /**
     * Up to five approved bank questions for this blueprint's course/role,
     * most-frequently-asked first, minus any already asked this session.
     */
    private function bankQuestions(MockInterview $interview, string $transcript): string
    {
        $blueprint = $interview->blueprint;

        $questions = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where(fn ($q) => $q
                ->where('course_id', $blueprint->course_id)
                ->orWhere('role_title', $blueprint->role_title))
            ->orderByDesc('asked_count')
            ->limit(10)
            ->pluck('question')
            ->reject(fn (string $question) => str_contains($transcript, $question))
            ->take(5);

        if ($questions->isEmpty()) {
            return '(none available)';
        }

        return $questions->map(fn (string $question) => '- '.$question)->implode("\n");
    }

    public function transcript(MockInterview $interview): string
    {
        return $interview->turns()->orderBy('id')->get()
            ->map(fn (MockTurn $turn) => strtoupper($turn->role).': '.$turn->body)
            ->implode("\n");
    }
}
