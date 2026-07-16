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

    public function shouldEscalate(): bool
    {
        return $this === self::Low;
    }
}
