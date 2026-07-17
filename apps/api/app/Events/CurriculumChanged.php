<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a course's curriculum tree mutates (module/topic/lesson created,
 * updated, or deleted). The syllabus regeneration listener recomputes the curriculum
 * hash and refreshes the draft when it changed (PRD §6.2 auto-regenerate).
 */
final class CurriculumChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $courseId,
        public readonly int $tenantId,
    ) {}
}
