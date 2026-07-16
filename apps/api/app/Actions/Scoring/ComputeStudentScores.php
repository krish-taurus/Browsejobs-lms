<?php

declare(strict_types=1);

namespace App\Actions\Scoring;

use App\Models\ScoreSnapshot;
use App\Models\StudentScore;
use App\Models\User;
use App\Support\Scoring\ScoreCalculator;
use App\Support\Tenancy\TenantContext;

/**
 * Computes a student's rule-based scores and persists them: upserts the latest
 * `student_scores` row + today's `score_snapshot` (PRD §6.4). Idempotent per day.
 */
final readonly class ComputeStudentScores
{
    public function __construct(private ScoreCalculator $calculator) {}

    public function handle(User $student): StudentScore
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student): StudentScore {
            $data = $this->calculator->computeFor($student);

            $score = StudentScore::query()->updateOrCreate(
                ['tenant_id' => $student->tenant_id, 'user_id' => $student->id],
                [
                    'engagement' => $data['engagement'],
                    'risk_dropout' => $data['risk_dropout'],
                    'risk_placement' => $data['risk_placement'],
                    'pri' => $data['pri'],
                    'streak_days' => $data['streak_days'],
                    'mastery' => $data['mastery'],
                    'next_action' => $data['next_action'],
                    'red_flags' => $data['red_flags'],
                    'computed_at' => now(),
                ],
            );

            ScoreSnapshot::query()->updateOrCreate(
                ['tenant_id' => $student->tenant_id, 'user_id' => $student->id, 'captured_on' => now()->startOfDay()],
                [
                    'pri' => $data['pri'],
                    'engagement' => $data['engagement'],
                    'risk_dropout' => $data['risk_dropout'],
                    'risk_placement' => $data['risk_placement'],
                ],
            );

            return $score;
        });
    }
}
