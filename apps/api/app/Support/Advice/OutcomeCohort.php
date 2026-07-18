<?php

declare(strict_types=1);

namespace App\Support\Advice;

use App\Models\Attendance;
use App\Models\JobApplication;
use App\Models\StudentScore;

/**
 * Builds the anonymised placement-outcome cohort for the current tenant (PRD
 * §6.21): one row per student who has entered the placement funnel, carrying only
 * their PRI-component signals and a placed/not-placed flag. No names or ids leave
 * this class — it exists to feed correlation math, not to expose individuals.
 */
final class OutcomeCohort
{
    /**
     * @return list<array{mastery: int, engagement: int, attendance: int, placed: bool}>
     */
    public function build(): array
    {
        // Students who have an outcome to learn from: anyone who applied at least once.
        $applications = JobApplication::query()
            ->select(['user_id', 'status'])
            ->get()
            ->groupBy('user_id');

        if ($applications->isEmpty()) {
            return [];
        }

        $userIds = $applications->keys()->all();

        $scores = StudentScore::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $attendance = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, AVG(attended_pct) as avg_pct')
            ->groupBy('user_id')
            ->pluck('avg_pct', 'user_id');

        $rows = [];
        foreach ($applications as $userId => $apps) {
            $score = $scores->get($userId);
            if ($score === null) {
                continue; // no computed score yet — can't place them in the cohort
            }

            $rows[] = [
                'mastery' => $this->avgMastery($score->mastery ?? []),
                'engagement' => (int) $score->engagement,
                'attendance' => (int) round((float) ($attendance[$userId] ?? 0)),
                'placed' => $apps->contains('status', JobApplication::STATUS_PLACED),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{pct: int}>  $mastery
     */
    private function avgMastery(array $mastery): int
    {
        if ($mastery === []) {
            return 0;
        }

        return (int) round(array_sum(array_column($mastery, 'pct')) / count($mastery));
    }
}
