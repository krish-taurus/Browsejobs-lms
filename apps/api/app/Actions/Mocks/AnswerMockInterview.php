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
        $max = (int) config('mocks.max_questions', 6);

        if ($asked >= $max) {
            return ['turn' => null, 'ready_to_finish' => true];
        }

        $blueprint = $interview->blueprint;
        $transcript = $this->transcript($interview);

        // v2 prompt (P4.2): the interviewer prefers questions asked in REAL
        // interviews for this role — the bank is the product's edge.
        $result = $this->gateway->complete($interview->student, AiPurpose::Mock, 'mock_interview', 2, [
            'role_title' => $blueprint->role_title,
            'competencies' => implode(', ', $blueprint->competencies),
            'remaining' => (string) max(0, $max - $asked - 1),
            'bank_questions' => $this->bankQuestions($interview, $transcript),
            'transcript' => $transcript,
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
