<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\PointsSource;
use App\Enums\QuizAttemptStatus;
use App\Models\QuizAttempt;
use App\Support\Points\PointsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Grades a student's quiz submission (PRD §6.5). Server-authoritative: rejects an
 * already-terminal attempt, marks a late submission `expired`, grades against the
 * stored `correct_index`, and records advisory integrity signals (never punishing).
 */
final readonly class SubmitQuizAttempt
{
    /** Grace for network latency on an auto-submit at the buzzer. */
    private const GRACE_SECONDS = 5;

    public function __construct(
        private RecordActivity $activity,
        private PointsService $points,
    ) {}

    /**
     * @param  array<string, int>  $answers  question_id => chosen option index
     * @param  array<string, int>  $integrity
     */
    public function handle(QuizAttempt $attempt, array $answers, array $integrity): QuizAttempt
    {
        return app(TenantContext::class)->run($attempt->tenant, function () use ($attempt, $answers, $integrity): QuizAttempt {
            if ($attempt->status->isTerminal()) {
                throw ValidationException::withMessages(['attempt' => 'This quiz has already been submitted.']);
            }

            $questions = $attempt->quiz->questions()->get(['id', 'correct_index']);
            $total = $questions->count();

            $correct = 0;
            foreach ($questions as $question) {
                if (isset($answers[(string) $question->id]) && (int) $answers[(string) $question->id] === $question->correct_index) {
                    $correct++;
                }
            }

            $late = $attempt->deadline_at !== null
                && now()->greaterThan($attempt->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));

            $attempt->update([
                'status' => $late ? QuizAttemptStatus::Expired->value : QuizAttemptStatus::Submitted->value,
                'submitted_at' => now(),
                'answers' => $answers,
                'integrity' => [
                    'tab_blurs' => (int) ($integrity['tab_blurs'] ?? 0),
                    'paste_count' => (int) ($integrity['paste_count'] ?? 0),
                    'duration_sec' => (int) ($integrity['duration_sec'] ?? 0),
                ],
                'correct_count' => $correct,
                'total_count' => $total,
                'score_pct' => $total === 0 ? 0 : (int) round($correct / $total * 100),
            ]);

            $attempt->refresh();

            // Telemetry + points (P3.6): on-time submissions only, keyed per
            // QUIZ (not attempt) so retakes can't farm points.
            if (! $late && $total > 0 && $attempt->tenant_id !== null) {
                $this->activity->handle(
                    $attempt->student,
                    ActivityType::QuizSubmitted,
                    $attempt->quiz->lesson,
                    $attempt->score_pct,
                    ['quiz_id' => $attempt->quiz_id],
                );

                if ($attempt->score_pct >= $attempt->quiz->pass_pct) {
                    $this->points->award(
                        $attempt->student,
                        PointsSource::Quiz,
                        "quiz:{$attempt->quiz_id}",
                        $this->points->settingsFor($attempt->tenant_id)->quiz_points,
                        $attempt->tenant_id,
                    );
                }

                if ($attempt->score_pct === 100) {
                    $this->points->grantBadge($attempt->student, 'module-ace', $attempt->tenant_id);
                }
            }

            return $attempt;
        });
    }
}
