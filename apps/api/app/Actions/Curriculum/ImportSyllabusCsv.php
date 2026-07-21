<?php

declare(strict_types=1);

namespace App\Actions\Curriculum;

use App\Enums\LessonType;
use App\Events\CurriculumChanged;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Build a whole syllabus from a flat CSV — Program → Course → Module → Topic →
 * Lesson — in one upload. Repeated parent names collapse into one node, order
 * follows the file, and re-importing is idempotent (existing nodes are reused,
 * never duplicated). Every topic is guaranteed a Quiz, an Assignment and a Mock
 * shell so the practice structure always exists, ready to be generated or
 * filled by hand.
 *
 * Expected headers (case-insensitive, extra columns ignored):
 *   program, course, module, topic, lesson_title, lesson_type
 */
final class ImportSyllabusCsv
{
    /** Auto-scaffolded practice lessons every topic gets. */
    private const AUTO = [
        ['suffix' => 'Quiz', 'type' => LessonType::Quiz],
        ['suffix' => 'Assignment', 'type' => LessonType::Assignment],
        ['suffix' => 'Mock', 'type' => LessonType::MockMilestone],
    ];

    /**
     * @param  list<array<string, string>>  $rows  parsed CSV rows keyed by header
     * @return array{programs:int, courses:int, modules:int, topics:int, lessons:int}
     */
    public function handle(Tenant $tenant, array $rows): array
    {
        $this->validate($rows);

        return DB::transaction(function () use ($tenant, $rows): array {
            $tid = (int) $tenant->id;
            $count = ['programs' => 0, 'courses' => 0, 'modules' => 0, 'topics' => 0, 'lessons' => 0];
            $programs = $courses = $modules = $topics = $seenTopics = [];
            $touchedCourseIds = [];

            foreach ($rows as $row) {
                $programName = trim($row['program']);
                $courseName = trim($row['course']);
                $moduleName = trim($row['module']);
                $topicName = trim($row['topic']);

                // Program
                $pSlug = Str::slug($programName);
                if (! isset($programs[$pSlug])) {
                    $program = Program::query()->firstOrCreate(
                        ['tenant_id' => $tid, 'slug' => $pSlug],
                        ['name' => $programName, 'position' => count($programs)],
                    );
                    $count['programs'] += $program->wasRecentlyCreated ? 1 : 0;
                    $programs[$pSlug] = $program;
                }
                $program = $programs[$pSlug];

                // Course (unique by code within tenant)
                $cCode = Str::slug($courseName);
                $cKey = $program->id.':'.$cCode;
                if (! isset($courses[$cKey])) {
                    $course = Course::query()->firstOrCreate(
                        ['tenant_id' => $tid, 'code' => $cCode],
                        [
                            'program_id' => $program->id,
                            'name' => $courseName,
                            'slug' => $cCode,
                            'status' => 'waitlist',
                            'position' => count($courses),
                        ],
                    );
                    $count['courses'] += $course->wasRecentlyCreated ? 1 : 0;
                    $courses[$cKey] = $course;
                }
                $course = $courses[$cKey];
                $touchedCourseIds[$course->id] = $course;

                // Module (by name within course)
                $mKey = $course->id.':'.$moduleName;
                if (! isset($modules[$mKey])) {
                    $module = Module::query()->firstOrCreate(
                        ['tenant_id' => $tid, 'course_id' => $course->id, 'name' => $moduleName],
                        ['position' => $course->modules()->count()],
                    );
                    $count['modules'] += $module->wasRecentlyCreated ? 1 : 0;
                    $modules[$mKey] = $module;
                }
                $module = $modules[$mKey];

                // Topic (by name within module)
                $tKey = $module->id.':'.$topicName;
                if (! isset($topics[$tKey])) {
                    $topic = Topic::query()->firstOrCreate(
                        ['tenant_id' => $tid, 'module_id' => $module->id, 'name' => $topicName],
                        ['position' => $module->topics()->count()],
                    );
                    $count['topics'] += $topic->wasRecentlyCreated ? 1 : 0;
                    $topics[$tKey] = $topic;
                }
                $topic = $topics[$tKey];

                // Explicit lesson from the row (optional).
                $title = trim($row['lesson_title'] ?? '');
                $typeRaw = trim($row['lesson_type'] ?? '');
                if ($title !== '' && $typeRaw !== '') {
                    $count['lessons'] += $this->ensureLesson($tid, $topic, $title, LessonType::from($typeRaw)) ? 1 : 0;
                }

                // Every topic always gets Quiz + Assignment + Mock shells (once).
                if (! isset($seenTopics[$topic->id])) {
                    $seenTopics[$topic->id] = true;
                    foreach (self::AUTO as $auto) {
                        $count['lessons'] += $this->ensureLesson(
                            $tid, $topic, "{$topic->name}: {$auto['suffix']}", $auto['type']
                        ) ? 1 : 0;
                    }
                }
            }

            foreach ($touchedCourseIds as $course) {
                CurriculumChanged::dispatch($course->id, $tid);
            }

            return $count;
        });
    }

    /** @return bool true when a new lesson was created */
    private function ensureLesson(int $tid, Topic $topic, string $title, LessonType $type): bool
    {
        $lesson = Lesson::query()->firstOrCreate(
            ['tenant_id' => $tid, 'topic_id' => $topic->id, 'title' => $title],
            ['type' => $type->value, 'position' => $topic->lessons()->count()],
        );

        return $lesson->wasRecentlyCreated;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function validate(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The file has no rows.']);
        }

        $errors = [];
        $valid = array_map(fn (LessonType $t) => $t->value, LessonType::cases());

        foreach ($rows as $i => $row) {
            $line = $i + 2; // +1 header, +1 to 1-index
            foreach (['program', 'course', 'module', 'topic'] as $col) {
                if (trim($row[$col] ?? '') === '') {
                    $errors[] = "Row {$line}: {$col} is required.";
                }
            }
            $type = trim($row['lesson_type'] ?? '');
            if ($type !== '' && ! in_array($type, $valid, true)) {
                $errors[] = "Row {$line}: lesson_type '{$type}' is not valid (use one of: ".implode(', ', $valid).').';
            }
            $title = trim($row['lesson_title'] ?? '');
            if ($title !== '' && $type === '') {
                $errors[] = "Row {$line}: lesson_title is set but lesson_type is empty.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
    }
}
