<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The AI Tutor's self-reported confidence in an answer (PRD §6.4). A `Low` answer
 * (or a missing/unparseable signal, which defaults to Low) escalates to the trainer.
 */
enum TutorConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /** Parse the model's trailing `CONFIDENCE:` line; unknown/missing → Low (fail-safe). */
    public static function fromLabel(?string $label): self
    {
        return match (strtolower(trim((string) $label))) {
            'high' => self::High,
            'medium' => self::Medium,
            default => self::Low,
        };
    }

    /**
     * Split a trailing `CONFIDENCE:` line off an answer. Shared by the tutor (§6.4) and
     * support deflection (§6.13) so there is exactly one fail-safe: a missing or
     * unparseable signal reads as Low, which escalates to a human in both callers.
     *
     * @return array{0: string, 1: self}
     */
    public static function split(string $text): array
    {
        $text = trim($text);
        $pattern = '/CONFIDENCE:\s*(high|medium|low)\s*$/i';

        if (preg_match($pattern, $text, $m) === 1) {
            return [trim(preg_replace($pattern, '', $text) ?? ''), self::fromLabel($m[1])];
        }

        return [$text, self::Low];
    }

    public function shouldEscalate(): bool
    {
        return $this === self::Low;
    }

    /** Ordinal for threshold comparisons: low < medium < high. */
    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2,
        };
    }

    /** Is this confidence at or above the given floor? */
    public function meets(self $floor): bool
    {
        return $this->rank() >= $floor->rank();
    }
}
