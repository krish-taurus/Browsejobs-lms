<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\AiPurpose;
use App\Enums\PointsSource;
use App\Events\MockCompleted;
use App\Models\CvProfile;
use App\Models\MockInterview;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Mocks\CompetencyCoverage;
use App\Support\Points\PointsService;
use Illuminate\Support\Str;
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

        // The AI Readiness Interview (blueprint->user_id set) is what makes a
        // student's profile discoverable in an employer's Talent Pool search
        // (LmsTalentMatcher) even without applying anywhere — best score
        // across attempts wins, same as RecordApplicationGrade.
        if ($interview->blueprint->user_id !== null) {
            $this->recordCvMockScore($student, (int) $scorecard['overall']);
        }

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
            $transcript = $this->answers->transcript($interview);
            // Which skills the conversation really touched. The grader is told, and
            // whatever it answers is checked against the same facts below, so a skill
            // nobody was asked about can never carry a score into a client's report.
            $coverage = CompetencyCoverage::check($blueprint->competencies, $transcript);
            $covered = array_keys(array_filter($coverage));
            $notCovered = array_keys(array_filter($coverage, fn (bool $c): bool => ! $c));

            $result = $this->gateway->complete($interview->student, AiPurpose::Mock, 'mock_scorecard', 2, [
                'role_title' => $blueprint->role_title,
                'competencies' => implode(', ', $blueprint->competencies),
                'covered' => $covered === [] ? '(none)' : implode(', ', $covered),
                'not_covered' => $notCovered === [] ? '(none)' : implode(', ', $notCovered),
                'transcript' => $transcript,
            ], ['max_tokens' => (int) config('mocks.scorecard_max_tokens', 4000)]);

            $decoded = JsonOutput::object($result->text);

            if (is_array($decoded) && $this->isValid($decoded)) {
                $decoded['overall'] = max(0, min(100, (int) $decoded['overall']));

                return [$this->onlyWhatWasAsked($decoded, $blueprint->competencies, $coverage), 'ai'];
            }
        } catch (Throwable) {
            // Budget/transport failure — deterministic fallback below.
        }

        return [$this->fallback($blueprint->competencies), 'fallback'];
    }

    /**
     * Keeps a score only for a skill that was talked about AND that the grader
     * could back with evidence. Everything else moves to `not_covered`, so it
     * shows as "not assessed" instead of as a number.
     *
     * @param  array<string, mixed>  $card
     * @param  list<string>  $planned
     * @param  array<string, bool>  $coverage
     * @return array<string, mixed>
     */
    private function onlyWhatWasAsked(array $card, array $planned, array $coverage): array
    {
        $assessed = [];
        $seen = [];

        foreach ((array) $card['competencies'] as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null)) {
                continue;
            }
            $name = $row['name'];
            $seen[] = Str::lower($name);

            $asked = ($row['asked'] ?? true) !== false;
            $talkedAbout = $coverage[$name] ?? collect($coverage)->first(fn (bool $c, string $k): bool => Str::lower($k) === Str::lower($name), true);
            if (! $asked || ! $talkedAbout || ! is_numeric($row['score'] ?? null)) {
                continue;
            }

            $assessed[] = array_filter([
                'name' => $name,
                'score' => max(0, min(100, (int) $row['score'])),
                'evidence' => is_string($row['evidence'] ?? null) && trim($row['evidence']) !== '' ? trim($row['evidence']) : null,
            ], fn ($v) => $v !== null);
        }

        $assessedNames = array_map(fn (array $r): string => Str::lower($r['name']), $assessed);
        $card['competencies'] = $assessed;
        $card['not_covered'] = array_values(array_filter($planned, fn (string $c): bool => ! in_array(Str::lower($c), $assessedNames, true)));

        return $card;
    }

    /**
     * Best score across attempts wins, same rule as the JD-mock application
     * grade — a weaker retake never demotes a student who already cleared a
     * higher bar. A CvProfile should already exist (starting this interview
     * requires one, mirroring the JD-mock CV gate); firstOrCreate is only a
     * safety net, not the expected path.
     */
    private function recordCvMockScore(User $student, int $score): void
    {
        $profile = CvProfile::query()->firstOrCreate(
            ['user_id' => $student->id],
            ['tenant_id' => $student->tenant_id, 'data' => CvProfile::EMPTY],
        );

        if ($profile->cv_mock_score === null || $score > $profile->cv_mock_score) {
            $profile->update(['cv_mock_score' => $score, 'cv_mock_completed_at' => now()]);
        }
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
