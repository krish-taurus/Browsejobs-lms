<?php

declare(strict_types=1);

namespace App\Enums;

enum MasteryBand: string
{
    case Weak = 'weak';
    case Developing = 'developing';
    case Strong = 'strong';

    public static function fromPct(int $pct): self
    {
        if ($pct < (int) config('scoring.mastery.weak_below', 40)) {
            return self::Weak;
        }

        return $pct > (int) config('scoring.mastery.strong_above', 70) ? self::Strong : self::Developing;
    }
}
