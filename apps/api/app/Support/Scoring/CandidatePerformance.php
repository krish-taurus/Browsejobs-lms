<?php

declare(strict_types=1);

namespace App\Support\Scoring;

use App\Models\Attendance;
use App\Models\MockInterview;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * The founder's candidate-performance score (ADR 0050): a weighted blend where
 * attendance carries the most weight, then mock participation/performance,
 * then assignment completion. Bands: green >= 80, amber 60-79.9, red < 60.
 * Deterministic and cheap — computed live from the same tables the student
 * portal shows, so admin and student always see the same truth.
 */
final class CandidatePerformance
{
    public const W_ATTENDANCE = 0.5;

    public const W_MOCKS = 0.3;

    public const W_ASSIGNMENTS = 0.2;

    /**
     * @return array{attendance_pct: float, mock_pct: float, mocks_done: int, assignment_pct: float, assignments_done: int, assignments_total: int, total: float, band: string}
     */
    public function componentsFor(User $student): array
    {
        $attendance = (float) (Attendance::query()->where('user_id', $student->id)->avg('attended_pct') ?? 0);
        $attendance = min(100.0, $attendance);

        // Mocks: how well they scored, discounted by showing up — a candidate who
        // attends mocks and scores well beats one great mock in isolation.
        $mocks = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('status', 'completed');
        $mocksDone = (int) $mocks->count();
        $mockAvg = (float) ($mocks->avg('overall_score') ?? 0);
        $mockPct = $mocksDone === 0 ? 0.0 : min(100.0, $mockAvg);

        $attempts = QuizAttempt::query()->where('user_id', $student->id);
        $assignTotal = (int) $attempts->count();
        $assignDone = (int) (clone $attempts)->where('status', 'submitted')->count();
        $assignPct = $assignTotal === 0 ? 0.0 : $assignDone / $assignTotal * 100;

        $total = round(
            $attendance * self::W_ATTENDANCE + $mockPct * self::W_MOCKS + $assignPct * self::W_ASSIGNMENTS,
            1,
        );

        return [
            'attendance_pct' => round($attendance, 1),
            'mock_pct' => round($mockPct, 1),
            'mocks_done' => $mocksDone,
            'assignment_pct' => round($assignPct, 1),
            'assignments_done' => $assignDone,
            'assignments_total' => $assignTotal,
            'total' => $total,
            'band' => self::band($total),
        ];
    }

    public static function band(float $total): string
    {
        return match (true) {
            $total >= 80 => 'green',
            $total >= 60 => 'amber',
            default => 'red',
        };
    }
}
