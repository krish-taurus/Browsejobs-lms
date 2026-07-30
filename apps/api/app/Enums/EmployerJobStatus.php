<?php

declare(strict_types=1);

namespace App\Enums;

/** Employer JD lifecycle (PRD-E F2). */
enum EmployerJobStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Closed = 'closed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Published,
            self::Published => in_array($target, [self::Paused, self::Closed], true),
            self::Paused => in_array($target, [self::Published, self::Closed], true),
            self::Closed => false,
        };
    }
}
