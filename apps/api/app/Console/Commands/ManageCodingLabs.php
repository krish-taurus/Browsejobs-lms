<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CodeLanguage;
use App\Enums\LessonType;
use App\Models\CodingLab;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * CRUD for coding labs, driven from the CRM (the founder never opens the LMS
 * admin panel).
 *
 * A lab hangs off a lesson of type `coding_lab`, and saving creates that lesson
 * when it doesn't exist yet — otherwise a trainer would have to build the
 * lesson in one place and the lab in another.
 *
 * Instructions, starter code and test cases arrive via files: they are far too
 * large for argv. `list` and `save` print JSON so the CRM can parse the result.
 */
final class ManageCodingLabs extends Command
{
    protected $signature = 'labs:manage
        {action : list, save or delete}
        {--lesson= : lesson id — save updates that lab, delete removes it}
        {--topic= : topic id to file a NEW lab under}
        {--title= : lesson title}
        {--language=python : python, sql, bash or javascript}
        {--instructions-file= : path to a UTF-8 text file}
        {--starter-file= : path to a UTF-8 text file}
        {--cases-file= : path to a JSON array of {stdin, expected_stdout}}
        {--time-limit= : milliseconds, 500-30000}
        {--tenant=1}';

    protected $description = 'List, save or delete coding labs';

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
                    'list' => $this->listLabs(),
                    'save' => $this->save($tenant),
                    'delete' => $this->deleteLab(),
                    default => $this->unknownAction(),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    private function listLabs(): int
    {
        $labs = CodingLab::query()
            ->with('lesson.topic.module.course')
            ->orderBy('id')
            ->get()
            ->map(fn (CodingLab $lab): array => [
                'lesson_id' => $lab->lesson_id,
                'title' => $lab->lesson?->title,
                'topic' => $lab->lesson?->topic?->name,
                'course' => $lab->lesson?->topic?->module?->course?->name,
                'language' => $lab->language->value,
                'cases' => count($lab->test_cases ?? []),
                'time_limit_ms' => $lab->time_limit_ms,
                'instructions' => $lab->instructions,
                'starter_code' => $lab->starter_code,
                'test_cases' => $lab->test_cases ?? [],
            ])
            ->all();

        $this->line((string) json_encode($labs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function save(Tenant $tenant): int
    {
        $language = (string) $this->option('language');

        if (CodeLanguage::tryFrom($language) === null) {
            $this->error('Language must be one of: '.implode(', ', array_column(CodeLanguage::cases(), 'value')));

            return self::FAILURE;
        }

        $lesson = $this->resolveLesson($tenant);

        if ($lesson === null) {
            return self::FAILURE;
        }

        $cases = $this->testCases();

        if ($cases === null) {
            return self::FAILURE;
        }

        $timeLimit = $this->option('time-limit') !== null ? (int) $this->option('time-limit') : null;

        if ($timeLimit !== null && ($timeLimit < 500 || $timeLimit > 30000)) {
            $this->error('Time limit must be between 500 and 30000 ms.');

            return self::FAILURE;
        }

        $lab = CodingLab::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'tenant_id' => $lesson->tenant_id,
                'language' => $language,
                'instructions' => $this->fileContents('instructions-file'),
                'starter_code' => $this->fileContents('starter-file'),
                'test_cases' => $cases,
                'time_limit_ms' => $timeLimit,
            ],
        );

        $this->line((string) json_encode([
            'lesson_id' => $lab->lesson_id,
            'title' => $lesson->title,
            'cases' => count($cases),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** Find the lesson being edited, or create the coding-lab lesson for a new one. */
    private function resolveLesson(Tenant $tenant): ?Lesson
    {
        if ($this->option('lesson') !== null) {
            $lesson = Lesson::query()->find((int) $this->option('lesson'));

            if ($lesson === null) {
                $this->error('Lesson not found.');

                return null;
            }

            if ($this->option('title') !== null) {
                $lesson->forceFill(['title' => (string) $this->option('title')])->save();
            }

            return $lesson;
        }

        $title = trim((string) $this->option('title'));

        if ($title === '') {
            $this->error('A title is required for a new lab.');

            return null;
        }

        $topic = Topic::query()->find((int) $this->option('topic'));

        if ($topic === null) {
            $this->error('Pick the topic this lab belongs to.');

            return null;
        }

        return Lesson::query()->create([
            'tenant_id' => $tenant->id,
            'topic_id' => $topic->id,
            'title' => $title,
            'type' => LessonType::CodingLab->value,
            'position' => (int) Lesson::query()->where('topic_id', $topic->id)->max('position') + 1,
        ]);
    }

    /**
     * @return list<array{stdin: string, expected_stdout: string}>|null
     */
    private function testCases(): ?array
    {
        $path = $this->option('cases-file');

        if ($path === null) {
            return [];
        }

        if (! is_readable((string) $path)) {
            $this->error('Test-case file not readable.');

            return null;
        }

        $decoded = json_decode((string) file_get_contents((string) $path), true);

        if (! is_array($decoded)) {
            $this->error('Test cases must be a JSON array.');

            return null;
        }

        if (count($decoded) > 50) {
            $this->error('A lab can hold at most 50 test cases.');

            return null;
        }

        $cases = [];

        foreach ($decoded as $index => $case) {
            $expected = $case['expected_stdout'] ?? null;

            if (! is_string($expected) || $expected === '') {
                $this->error('Test case '.($index + 1).' has no expected output.');

                return null;
            }

            $cases[] = [
                'stdin' => (string) ($case['stdin'] ?? ''),
                'expected_stdout' => $expected,
            ];
        }

        return $cases;
    }

    private function deleteLab(): int
    {
        $lesson = Lesson::query()->find((int) $this->option('lesson'));

        if ($lesson === null) {
            $this->error('Lesson not found.');

            return self::FAILURE;
        }

        CodingLab::query()->where('lesson_id', $lesson->id)->delete();

        // A coding-lab lesson with no lab left is an empty shell in the syllabus.
        if ($lesson->type === LessonType::CodingLab) {
            $lesson->delete();
        }

        $this->line((string) json_encode(['deleted' => true], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function fileContents(string $option): ?string
    {
        $path = $this->option($option);

        if ($path === null || ! is_readable((string) $path)) {
            return null;
        }

        return (string) file_get_contents((string) $path);
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action — use list, save or delete.');

        return self::FAILURE;
    }
}
