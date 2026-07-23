<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Models\MockInterview;
use App\Models\StudentScore;
use App\Models\User;

/**
 * "What are my chances of cracking this interview?" (ADR 0048). Blends the
 * job-side match % with what we actually know about the student: their cached
 * PRI (nightly ScoreCalculator batch — never recomputed on a hot path) and
 * their best completed mock score. Signals that don't exist yet simply drop
 * out of the blend, and `basis` says what the number is built on so the UI can
 * say "take a mock to firm this up" instead of pretending precision.
 */
final class ConfidenceScorer
{
    private const WEIGHT_MATCH = 0.5;

    private const WEIGHT_PRI = 0.3;

    private const WEIGHT_MOCK = 0.2;

    private ?int $pri = null;

    private ?int $bestMock = null;

    private bool $loaded = false;

    public function __construct(private readonly User $student) {}

    /**
     * @return array{confidence_pct: int, based_on: list<string>}
     */
    public function for(int $matchPct): array
    {
        $this->load();

        $signals = [['match', self::WEIGHT_MATCH, $matchPct]];
        $basedOn = ['skill match'];

        if ($this->pri !== null) {
            $signals[] = ['pri', self::WEIGHT_PRI, $this->pri];
            $basedOn[] = 'course progress';
        }
        if ($this->bestMock !== null) {
            $signals[] = ['mock', self::WEIGHT_MOCK, $this->bestMock];
            $basedOn[] = 'mock performance';
        }

        // Renormalise over the signals we actually have.
        $totalWeight = array_sum(array_column($signals, 1));
        $score = 0.0;
        foreach ($signals as [, $weight, $value]) {
            $score += ($weight / $totalWeight) * $value;
        }

        return [
            'confidence_pct' => max(0, min(100, (int) round($score))),
            'based_on' => $basedOn,
        ];
    }

    public function hasMock(): bool
    {
        $this->load();

        return $this->bestMock !== null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $this->pri = StudentScore::query()
            ->where('user_id', $this->student->id)
            ->value('pri');

        $best = MockInterview::query()
            ->where('user_id', $this->student->id)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->max('overall_score');
        $this->bestMock = $best !== null ? (int) $best : null;
    }
}
