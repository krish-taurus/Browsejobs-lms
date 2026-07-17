<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A grade's release state (PRD §6.5 / §7 human checkpoint). A `Draft` grade (AI or
 * manual) is trainer-only; the student sees it only once `Approved`.
 */
enum GradeStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
