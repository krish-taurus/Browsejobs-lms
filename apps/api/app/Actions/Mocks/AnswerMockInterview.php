<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\AiPurpose;
use App\Models\MockInterview;
use App\Models\MockTurn;
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

        $result = $this->gateway->complete($interview->student, AiPurpose::Mock, 'mock_interview', 1, [
            'role_title' => $blueprint->role_title,
            'competencies' => implode(', ', $blueprint->competencies),
            'remaining' => (string) max(0, $max - $asked - 1),
            'transcript' => $this->transcript($interview),
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

    public function transcript(MockInterview $interview): string
    {
        return $interview->turns()->orderBy('id')->get()
            ->map(fn (MockTurn $turn) => strtoupper($turn->role).': '.$turn->body)
            ->implode("\n");
    }
}
