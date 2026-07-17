<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a student completes every topic in every module of a course (PRD §6.5).
 * Drives certificate auto-issue.
 */
final class CourseCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Course $course,
    ) {}
}
