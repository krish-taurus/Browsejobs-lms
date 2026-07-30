<?php

declare(strict_types=1);

namespace App\Enums;

/** Interview rounds an application moves through (PRD-E F5). */
enum EmployerInterviewRound: string
{
    case L1 = 'l1';
    case L2 = 'l2';

    public function stage(): EmployerApplicationStage
    {
        return match ($this) {
            self::L1 => EmployerApplicationStage::L1,
            self::L2 => EmployerApplicationStage::L2,
        };
    }

    public static function forStage(EmployerApplicationStage $stage): ?self
    {
        return match ($stage) {
            EmployerApplicationStage::L1 => self::L1,
            EmployerApplicationStage::L2 => self::L2,
            default => null,
        };
    }
}
