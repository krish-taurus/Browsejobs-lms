<?php

declare(strict_types=1);

namespace App\Support\Learning;

/**
 * SM-2 spaced-repetition scheduler (the SuperMemo-2 / Anki algorithm). Given a
 * card's current schedule and how the student rated this review, it returns the
 * next ease factor, interval, and repetition count — so weak cards resurface
 * soon and mastered ones stretch out. Pure and deterministic: no I/O, no clock.
 *
 * Quality scale 0–5. The app's rating buttons map on: Again→1, Hard→3, Good→4,
 * Easy→5. A quality below 3 is a lapse: reps reset and the card is due again
 * tomorrow.
 */
final class Sm2
{
    public const EASE_DEFAULT = 2.5;

    public const EASE_MIN = 1.3;

    /** UI rating → SM-2 quality. */
    public const RATINGS = ['again' => 1, 'hard' => 3, 'good' => 4, 'easy' => 5];

    /**
     * @return array{ease: float, interval_days: int, reps: int, lapsed: bool}
     */
    public static function schedule(float $ease, int $intervalDays, int $reps, int $quality): array
    {
        $quality = max(0, min(5, $quality));
        $lapsed = $quality < 3;

        if ($lapsed) {
            $reps = 0;
            $intervalDays = 1;
        } else {
            $reps++;
            $intervalDays = match (true) {
                $reps === 1 => 1,
                $reps === 2 => 6,
                default => (int) round($intervalDays * $ease),
            };
        }

        // Update the ease factor (classic SM-2 formula) and floor it.
        $ease = $ease + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        $ease = max(self::EASE_MIN, round($ease, 2));

        return [
            'ease' => $ease,
            'interval_days' => max(1, $intervalDays),
            'reps' => $reps,
            'lapsed' => $lapsed,
        ];
    }

    /** Map a UI rating word to its SM-2 quality, or null if unknown. */
    public static function qualityFor(string $rating): ?int
    {
        return self::RATINGS[$rating] ?? null;
    }
}
