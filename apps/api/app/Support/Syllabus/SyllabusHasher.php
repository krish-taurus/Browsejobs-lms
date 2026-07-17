<?php

declare(strict_types=1);

namespace App\Support\Syllabus;

use App\Enums\LessonType;
use App\Models\Course;

/**
 * Deterministic fingerprint of a course's curriculum tree + descriptive meta
 * (PRD §6.2 "auto-regenerates when curriculum version changes"). A changed hash
 * means the syllabus is stale and should be regenerated. Also exposes the readable
 * outline the AI generator feeds to the prompt, so both stay in sync.
 */
final class SyllabusHasher
{
    /** Ordered, human-readable outline of the course tree (modules → topics → lessons). */
    public function outline(Course $course): string
    {
        $course->loadMissing(['modules' => fn ($q) => $q->orderBy('position'),
            'modules.topics' => fn ($q) => $q->orderBy('position'),
            'modules.topics.lessons' => fn ($q) => $q->orderBy('position')]);

        $lines = [];
        $moduleNo = 0;
        foreach ($course->modules as $module) {
            $moduleNo++;
            $lines[] = "Module {$moduleNo}: {$module->name}";
            foreach ($module->topics as $topic) {
                $lines[] = "  - {$topic->name}";
                foreach ($topic->lessons as $lesson) {
                    $type = $lesson->type instanceof LessonType ? $lesson->type->value : (string) $lesson->type;
                    $lines[] = "      . {$lesson->title} ({$type})";
                }
            }
        }

        return implode("\n", $lines);
    }

    public function hash(Course $course): string
    {
        $material = implode("\n", [
            (string) $course->name,
            (string) $course->tagline,
            (string) $course->description,
            $this->outline($course),
        ]);

        return hash('sha256', $material);
    }
}
