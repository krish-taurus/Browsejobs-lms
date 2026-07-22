<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\AiPurpose;
use App\Enums\PointsSource;
use App\Events\MockCompleted;
use App\Models\MockInterview;
use App\Services\AI\AiGateway;
use App\Support\Points\PointsService;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Closes a mock and generates the scorecard (PRD §6.6): per-competency
 * scores, strong/weak moments, model answers, exactly three actions. Feeds
 * PRI (via ScoreCalculator's mock blend), telemetry, points (keyed per
 * interview) and the First Mock Done badge. Fails safe to a deterministic
 * card so finishing never dead-ends.
 */
final readonly class FinishMockInterview
{
    public function __construct(
        private AiGateway $gateway,
        private AnswerMockInterview $answers,
        private RecordActivity $activity,
        private PointsService $points,
    ) {}

    public function handle(MockInterview $interview): MockInterview
    {
        if ($interview->status === MockInterview::STATUS_COMPLETED) {
            return $interview; // Idempotent — the card is already on record.
        }

        $answered = $interview->candidateAnswers();
        if ($answered < (int) config('mocks.min_answers', 2)) {
            throw ValidationException::withMessages([
                'mock' => 'Answer at least '.config('mocks.min_answers', 2).' questions before finishing.',
            ]);
        }

        [$scorecard, $source] = $this->scorecard($interview);

        $interview->update([
            'status' => MockInterview::STATUS_COMPLETED,
            'completed_at' => now(),
            'overall_score' => $scorecard['overall'],
            'scorecard' => $scorecard,
            'scorecard_source' => $source,
        ]);

        $student = $interview->student;
        $tenantId = (int) $interview->tenant_id;

        $this->activity->handle($student, ActivityType::MockCompleted, $interview, (int) $scorecard['overall']);

        $this->points->award(
            $student,
            PointsSource::Mock,
            "mock:{$interview->id}",
            $this->points->settingsFor($tenantId)->mock_points,
            $tenantId,
        );
        $this->points->grantBadge($student, 'first-mock-done', $tenantId);

        // Fires once per interview (this method is idempotent above), so
        // module-mock progress can never be double-counted.
        MockCompleted::dispatch($student, $interview);

        return $interview->refresh();
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function scorecard(MockInterview $interview): array
    {
        $blueprint = $interview->blueprint;

        try {
            $result = $this->gateway->complete($interview->student, AiPurpose::Mock, 'mock_scorecard', 1, [
                'role_title' => $blueprint->role_title,
                'competencies' => implode(', ', $blueprint->competencies),
                'transcript' => $this->answers->transcript($interview),
            ], ['max_tokens' => 900]);

            $decoded = json_decode(trim($result->text), true);

            if (is_array($decoded) && $this->isValid($decoded)) {
                $decoded['overall'] = max(0, min(100, (int) $decoded['overall']));

                return [$decoded, 'ai'];
            }
        } catch (Throwable) {
            // Budget/transport failure — deterministic fallback below.
        }

        return [$this->fallback($blueprint->competencies), 'fallback'];
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function isValid(array $card): bool
    {
        return isset($card['overall'], $card['competencies'], $card['actions'])
            && is_numeric($card['overall'])
            && is_array($card['competencies'])
            && is_array($card['actions'])
            && count($card['actions']) >= 3;
    }

    /**
     * @param  list<string>  $competencies
     * @return array<string, mixed>
     */
    private function fallback(array $competencies): array
    {
        return [
            // Deliberately conservative: an ungraded session must never
            // inflate PRI or unlock the human-mock gate.
            'overall' => 40,
            'competencies' => array_map(fn (string $c) => ['name' => $c, 'score' => 40], $competencies),
            'strong_moments' => ['You completed the interview — showing up is the habit that compounds.'],
            'weak_moments' => ['Automated grading was unavailable for this session, so it is scored conservatively.'],
            'model_answers' => [],
            'actions' => [
                'Retake a mock this week so the interviewer can grade you properly.',
                'Rewatch the recording of your weakest module and redo its lab.',
                'Practise answering out loud: one minute of structure before detail.',
            ],
        ];
    }
}
