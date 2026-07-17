<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A course syllabus's approval state (PRD §6.2). Only an `Approved` syllabus is
 * rendered and downloadable (public lead-gated + student dashboard); editing an
 * approved syllabus reverts it to `Draft`.
 */
enum SyllabusStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
