<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A quiz's approval state (PRD §6.5). Only an `Approved` quiz dispatches to students;
 * editing an approved quiz reverts it to `Draft`.
 */
enum QuizStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
