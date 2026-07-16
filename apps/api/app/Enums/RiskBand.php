<?php

declare(strict_types=1);

namespace App\Enums;

enum RiskBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 66 => self::High,
            $score >= 33 => self::Medium,
            default => self::Low,
        };
    }
}
