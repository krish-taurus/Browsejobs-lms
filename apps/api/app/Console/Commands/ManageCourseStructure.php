<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * The curriculum tree, driven from the CRM.
 *
 * What the CRM calls a "subject" is a module and a "chapter" is a topic — the
 * syllabus has always had both levels, but nothing outside the LMS admin panel
 * could edit them, so courses were stuck with whatever the seeder created.
 *
 * Deletes refuse to run when there is content underneath: a topic holding
 * lessons is also holding that lesson's quizzes, assignments and every
 * student's completion record, and none of that should disappear because
 * somebody tidied the syllabus.
 */
final class ManageCourseStructure extends Command
{
    protected $signature = 'course:structure
        {action : tree, save-subject, save-chapter, delete-subject, delete-chapter or reorder}
        {--course= : course id or slug}
        {--subject= : module id, for save-chapter and subject edits}
        {--chapter= : topic id, when editing an existing chapter}
        {--name= : subject or chapter name}
        {--position= : sort position}
        {--summary-file= : path to a UTF-8 text file holding a chapter summary}
        {--order= : comma-separated ids, in the order you want them}
        {--tenant=1}';

    protected $description = 'Read or edit a course syllabus — its subjects and their chapters';

    public function handle(): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($tenant): int {
            try {
                return match ($this->argument('action')) {
                    'tree' => $this->tree(),
                    'save-subject' => $this->saveSubject($tenant),
                    'save-chapter' => $this->saveChapter($tenant),
                    'delete-subject' => $this->deleteSubject(),
                    'delete-chapter' => $this->deleteChapter(),
                    'reorder' => $this->reorder(),
                    default => $this->unknownAction(),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    /** The whole syllabus for one course (or every course), with content counts. */
    private function tree(): int
    {
        $courses = Course::query()
            ->when($this->option('course') !== null, fn ($q) => $this->scopeToCourse($q))
            ->orderBy('name')
            ->get();

        $out = $courses->map(function (Course $course): array {
            $subjects = Module::query()
                ->where('course_id', $course->id)
                ->orderBy('position')->orderBy('id')
                ->get();

            return [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'fee_paise' => $course->fee_paise,
                'subjects' => $subjects->map(function (Module $module): array {
                    $chapters = Topic::query()
                        ->where('module_id', $module->id)
                        ->orderBy('position')->orderBy('id')
                        ->get();

                    return [
                        'id' => $module->id,
                        'name' => $module->name,
                        'position' => $module->position,
                        'chapters' => $chapters->map(fn (Topic $topic): array => [
                            'id' => $topic->id,
                            'name' => $topic->name,
                            'position' => $topic->position,
                            'summary' => $topic->summary,
                            // What would be lost if this chapter were deleted.
                            'lessons' => Lesson::query()->where('topic_id', $topic->id)->count(),
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->all();

        $this->line((string) json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function saveSubject(Tenant $tenant): int
    {
        $name = trim((string) $this->option('name'));
        $subject = $this->option('subject') !== null
            ? Module::query()->find((int) $this->option('subject'))
            : null;

        if ($this->option('subject') !== null && $subject === null) {
            $this->error('Subject not found.');

            return self::FAILURE;
        }

        if ($subject === null) {
            $course = $this->course();

            if ($course === null) {
                $this->error('Pick the course this subject belongs to.');

                return self::FAILURE;
            }

            if ($name === '') {
                $this->error('A name is required for a new subject.');

                return self::FAILURE;
            }

            $subject = new Module(['tenant_id' => $tenant->id, 'course_id' => $course->id]);
            // New subjects land at the end rather than colliding with position 0.
            $subject->position = $this->option('position') !== null
                ? (int) $this->option('position')
                : (int) Module::query()->where('course_id', $course->id)->max('position') + 1;
        } elseif ($this->option('position') !== null) {
            $subject->position = (int) $this->option('position');
        }

        if ($name !== '') {
            $subject->name = $name;
        }

        $subject->save();

        return $this->ok(['id' => $subject->id, 'name' => $subject->name, 'position' => $subject->position]);
    }

    private function saveChapter(Tenant $tenant): int
    {
        $name = trim((string) $this->option('name'));
        $chapter = $this->option('chapter') !== null
            ? Topic::query()->find((int) $this->option('chapter'))
            : null;

        if ($this->option('chapter') !== null && $chapter === null) {
            $this->error('Chapter not found.');

            return self::FAILURE;
        }

        if ($chapter === null) {
            $subject = Module::query()->find((int) $this->option('subject'));

            if ($subject === null) {
                $this->error('Pick the subject this chapter belongs to.');

                return self::FAILURE;
            }

            if ($name === '') {
                $this->error('A name is required for a new chapter.');

                return self::FAILURE;
            }

            $chapter = new Topic(['tenant_id' => $tenant->id, 'module_id' => $subject->id]);
            $chapter->position = $this->option('position') !== null
                ? (int) $this->option('position')
                : (int) Topic::query()->where('module_id', $subject->id)->max('position') + 1;
        } elseif ($this->option('position') !== null) {
            $chapter->position = (int) $this->option('position');
        }

        if ($name !== '') {
            $chapter->name = $name;
        }

        if ($this->option('summary-file') !== null) {
            $path = (string) $this->option('summary-file');
            $chapter->summary = is_readable($path) ? trim((string) file_get_contents($path)) : null;
        }

        $chapter->save();

        return $this->ok(['id' => $chapter->id, 'name' => $chapter->name, 'position' => $chapter->position]);
    }

    private function deleteSubject(): int
    {
        $subject = Module::query()->find((int) $this->option('subject'));

        if ($subject === null) {
            $this->error('Subject not found.');

            return self::FAILURE;
        }

        $chapters = Topic::query()->where('module_id', $subject->id)->count();

        if ($chapters > 0) {
            $this->error("This subject still has {$chapters} chapter(s). Delete those first.");

            return self::FAILURE;
        }

        $subject->delete();

        return $this->ok(['deleted' => true]);
    }

    private function deleteChapter(): int
    {
        $chapter = Topic::query()->find((int) $this->option('chapter'));

        if ($chapter === null) {
            $this->error('Chapter not found.');

            return self::FAILURE;
        }

        $lessons = Lesson::query()->where('topic_id', $chapter->id)->count();

        if ($lessons > 0) {
            $this->error("This chapter holds {$lessons} lesson(s) — with their quizzes, assignments and student progress. Move or delete those first.");

            return self::FAILURE;
        }

        $chapter->delete();

        return $this->ok(['deleted' => true]);
    }

    /** Rewrite the order of a course's subjects, or one subject's chapters. */
    private function reorder(): int
    {
        $ids = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('order'))
        )));

        if ($ids === []) {
            $this->error('Pass --order with a comma-separated list of ids.');

            return self::FAILURE;
        }

        $model = $this->option('subject') !== null ? Topic::class : Module::class;

        foreach ($ids as $position => $id) {
            $model::query()->whereKey($id)->update(['position' => $position]);
        }

        return $this->ok(['reordered' => count($ids)]);
    }

    private function course(): ?Course
    {
        $reference = trim((string) $this->option('course'));

        if ($reference === '') {
            return null;
        }

        return Course::query()
            ->when(
                is_numeric($reference),
                fn ($q) => $q->whereKey((int) $reference),
                fn ($q) => $q->where('slug', $reference),
            )
            ->first();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Course>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Course>
     */
    private function scopeToCourse($query)
    {
        $reference = trim((string) $this->option('course'));

        return is_numeric($reference)
            ? $query->whereKey((int) $reference)
            : $query->where('slug', $reference);
    }

    /** @param array<string, mixed> $payload */
    private function ok(array $payload): int
    {
        $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action — use tree, save-subject, save-chapter, delete-subject, delete-chapter or reorder.');

        return self::FAILURE;
    }
}
