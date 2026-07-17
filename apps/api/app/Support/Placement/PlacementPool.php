<?php

declare(strict_types=1);

namespace App\Support\Placement;

use App\Models\CvDocument;
use App\Models\MentorSession;
use App\Models\User;
use App\Support\Scoring\ScoreCalculator;

/**
 * The placement-pool gate (PRD §6.11): PRI at threshold + an APPROVED CV +
 * a passed human mock (a completed placement interview with mentor feedback
 * at the bar). The same checks power the student checklist and the
 * server-side application guard — the UI can never promise what the API
 * refuses.
 */
final readonly class PlacementPool
{
    public function __construct(private ScoreCalculator $scores) {}

    /**
     * @return array{eligible: bool, checks: array<string, array{ok: bool, value: int|null, required: int|null, label: string}>}
     */
    public function statusFor(User $student): array
    {
        $pri = (int) $this->scores->computeFor($student)['pri'];
        $minPri = (int) config('placement.pool.min_pri', 70);

        $hasApprovedCv = CvDocument::query()
            ->where('user_id', $student->id)
            ->where('status', CvDocument::STATUS_APPROVED)
            ->exists();

        $bestHumanMock = MentorSession::query()
            ->where('student_id', $student->id)
            ->where('purpose', MentorSession::PURPOSE_PLACEMENT)
            ->where('status', MentorSession::STATUS_COMPLETED)
            ->max('feedback_score');
        $minHuman = (int) config('placement.pool.min_human_mock_score', 70);

        $checks = [
            'pri' => [
                'ok' => $pri >= $minPri,
                'value' => $pri,
                'required' => $minPri,
                'label' => 'Placement Readiness Index',
            ],
            'cv' => [
                'ok' => $hasApprovedCv,
                'value' => null,
                'required' => null,
                'label' => 'CV approved by the placement team',
            ],
            'human_mock' => [
                'ok' => $bestHumanMock !== null && (int) $bestHumanMock >= $minHuman,
                'value' => $bestHumanMock !== null ? (int) $bestHumanMock : null,
                'required' => $minHuman,
                'label' => 'Human mock interview passed',
            ],
        ];

        return [
            'eligible' => collect($checks)->every(fn (array $c) => $c['ok']),
            'checks' => $checks,
        ];
    }
}
