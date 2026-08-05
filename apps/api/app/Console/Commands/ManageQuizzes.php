<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LessonType;
use App\Enums\QuizStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * CRUD for quizzes and their questions, driven from the CRM.
 *
 * A quiz hangs off a lesson of type `quiz`, so saving a new one creates that
 * lesson under the chosen topic — the trainer never has to build the syllabus
 * entry separately.
 *
 * A quiz only reaches students once it is APPROVED and has at least one
 * question ({@see Quiz::isDispatchable()}), so `save` keeps it in draft until
 * questions exist and the CRM explicitly approves it.
 *
 * Questions arrive as a JSON file: one object per question with `prompt`,
 * `options` (2–6 strings) and `correct_index`.
 */
final class ManageQuizzes extends Command
{
    protected $signature = 'quiz:manage
        {action : list, save, delete or approve}
        {--quiz= : quiz id for save, delete or approve}
        {--course= : course id or slug for a NEW quiz}
        {--module= : module id for a NEW quiz, so it files under the module taught}
        {--topic= : topic id, if you want a specific one}
        {--title= : quiz title}
        {--instructions-file= : path to a UTF-8 text file}
        {--questions-file= : path to a JSON array of {prompt, options[], correct_index, explanation?}}
        {--minutes= : time limit in minutes}
        {--pass= : pass percentage, 1-100}
        {--shuffle=1 : 1 or 0}
        {--tenant=1}';

    protected $description = 'List, save, approve or delete quizzes';

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
                    'list' => $this->listQuizzes(),
                    'save' => $this->save($tenant),
                    'delete' => $this->deleteQuiz(),
                    'approve' => $this->approve(),
                    default => $this->unknownAction(),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    private function listQuizzes(): int
    {
        $rows = Quiz::query()
            ->with(['lesson.topic.module.course', 'questions'])
            ->withCount(['questions', 'attempts'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Quiz $q): array => [
                'id' => $q->id,
                'lesson_id' => $q->lesson_id,
                'title' => $q->title,
                'course' => $q->lesson?->topic?->module?->course?->name,
                'topic' => $q->lesson?->topic?->name,
                'minutes' => (int) round(($q->time_limit_sec ?: 0) / 60),
                'pass_pct' => $q->pass_pct,
                'shuffle' => (bool) $q->shuffle,
                'status' => $q->status instanceof QuizStatus ? $q->status->value : (string) $q->status,
                'questions' => $q->questions_count,
                'attempts' => $q->attempts_count,
                'instructions' => $q->instructions,
                'question_list' => $q->questions
                    ->sortBy('position')
                    ->map(fn (QuizQuestion $qq): array => [
                        'prompt' => $qq->prompt,
                        'options' => $qq->options,
                        'correct_index' => $qq->correct_index,
                        'explanation' => $qq->explanation,
                    ])->values()->all(),
            ])
            ->all();

        $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function save(Tenant $tenant): int
    {
        $quiz = $this->option('quiz') !== null
            ? Quiz::query()->find((int) $this->option('quiz'))
            : null;

        if ($this->option('quiz') !== null && $quiz === null) {
            $this->error('Quiz not found.');

            return self::FAILURE;
        }

        $title = trim((string) $this->option('title'));

        if ($quiz === null) {
            if ($title === '') {
                $this->error('A title is required for a new quiz.');

                return self::FAILURE;
            }

            $topic = $this->resolveTopic($tenant);

            if ($topic === null) {
                $this->error('Pick the course this quiz belongs to.');

                return self::FAILURE;
            }

            $lesson = Lesson::query()->create([
                'tenant_id' => $tenant->id,
                'topic_id' => $topic->id,
                'title' => $title,
                'type' => LessonType::Quiz->value,
                'position' => (int) Lesson::query()->where('topic_id', $topic->id)->max('position') + 1,
            ]);

            $quiz = new Quiz(['tenant_id' => $tenant->id, 'lesson_id' => $lesson->id]);
        } elseif ($title !== '') {
            $quiz->title = $title;
            $quiz->lesson?->forceFill(['title' => $title])->save();
        }

        $quiz->title = $quiz->title ?: $title;

        if ($this->option('instructions-file') !== null) {
            $quiz->instructions = $this->fileContents('instructions-file');
        }

        if ($this->option('minutes') !== null) {
            $quiz->time_limit_sec = max(60, (int) $this->option('minutes') * 60);
        }

        if ($this->option('pass') !== null) {
            $quiz->pass_pct = min(100, max(1, (int) $this->option('pass')));
        }

        $quiz->time_limit_sec = $quiz->time_limit_sec ?: 600;
        $quiz->pass_pct = $quiz->pass_pct ?: 60;
        $quiz->shuffle = (bool) (int) $this->option('shuffle');
        // Editing content pulls an approved quiz back to draft: students must
        // never receive a version nobody signed off.
        $quiz->status = QuizStatus::Draft;
        $quiz->save();

        $written = $this->syncQuestions($quiz);

        if ($written === null) {
            return self::FAILURE;
        }

        $this->line((string) json_encode([
            'id' => $quiz->id,
            'lesson_id' => $quiz->lesson_id,
            'questions' => $written,
            'status' => 'draft',
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * Where to hang the quiz lesson.
     *
     * A quiz belongs to a module as far as anyone using the CRM is concerned
     * ("the Python test", "the SQL test"), but the syllabus stores lessons under
     * topics. So a module is resolved to one of its topics — a new "Assessments"
     * topic when it has none — which keeps the quiz reportable as that module's.
     * A bare course still works, and courses with no modules at all get an
     * "Assessments" module on demand rather than being unusable.
     */
    private function resolveTopic(Tenant $tenant): ?Topic
    {
        if ($this->option('topic') !== null) {
            return Topic::query()->find((int) $this->option('topic'));
        }

        if ($this->option('module') !== null) {
            $module = Module::query()->find((int) $this->option('module'));

            return $module === null ? null : $this->topicFor($module, $tenant);
        }

        $reference = trim((string) $this->option('course'));

        if ($reference === '') {
            return null;
        }

        $course = Course::query()
            ->when(
                is_numeric($reference),
                fn ($q) => $q->whereKey((int) $reference),
                fn ($q) => $q->where('slug', $reference),
            )
            ->first();

        if ($course === null) {
            return null;
        }

        $existing = Topic::query()
            ->whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $module = Module::query()->firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Assessments'],
            ['tenant_id' => $tenant->id, 'position' => 99],
        );

        return $this->topicFor($module, $tenant);
    }

    /** A module's own topic to hang the quiz lesson under, created if it has none. */
    private function topicFor(Module $module, Tenant $tenant): Topic
    {
        $existing = Topic::query()->where('module_id', $module->id)->orderBy('position')->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return Topic::query()->firstOrCreate(
            ['module_id' => $module->id, 'name' => 'Assessments'],
            ['tenant_id' => $tenant->id, 'position' => 0],
        );
    }

    /** @return int|null number of questions stored, or null on a bad file */
    private function syncQuestions(Quiz $quiz): ?int
    {
        $path = $this->option('questions-file');

        if ($path === null) {
            return $quiz->questions()->count();
        }

        if (! is_readable((string) $path)) {
            $this->error('Questions file not readable.');

            return null;
        }

        $decoded = json_decode((string) file_get_contents((string) $path), true);

        if (! is_array($decoded)) {
            $this->error('Questions must be a JSON array.');

            return null;
        }

        $rows = [];

        foreach ($decoded as $index => $question) {
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $options = $question['options'] ?? [];
            $correct = $question['correct_index'] ?? null;

            if ($prompt === '') {
                $this->error('Question '.($index + 1).' has no text.');

                return null;
            }

            if (! is_array($options) || count($options) < 2 || count($options) > 6) {
                $this->error('Question '.($index + 1).' needs between 2 and 6 options.');

                return null;
            }

            if (! is_int($correct) || $correct < 0 || $correct >= count($options)) {
                $this->error('Question '.($index + 1).' has no valid correct answer.');

                return null;
            }

            $rows[] = [
                'tenant_id' => $quiz->tenant_id,
                'quiz_id' => $quiz->id,
                'prompt' => $prompt,
                'options' => array_values(array_map('strval', $options)),
                'correct_index' => $correct,
                'explanation' => $question['explanation'] ?? null,
                'position' => $index,
            ];
        }

        // Replace wholesale: the CRM always posts the full list, and matching
        // rows up one by one would leave orphans behind when a question is removed.
        $quiz->questions()->delete();

        foreach ($rows as $row) {
            QuizQuestion::query()->create($row);
        }

        return count($rows);
    }

    private function approve(): int
    {
        $quiz = Quiz::query()->withCount('questions')->find((int) $this->option('quiz'));

        if ($quiz === null) {
            $this->error('Quiz not found.');

            return self::FAILURE;
        }

        if ($quiz->questions_count === 0) {
            $this->error('Add at least one question before approving.');

            return self::FAILURE;
        }

        $quiz->forceFill(['status' => QuizStatus::Approved->value, 'approved_at' => now()])->save();

        $this->line((string) json_encode(['id' => $quiz->id, 'status' => 'approved'], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function deleteQuiz(): int
    {
        $quiz = Quiz::query()->withCount('attempts')->find((int) $this->option('quiz'));

        if ($quiz === null) {
            $this->error('Quiz not found.');

            return self::FAILURE;
        }

        if ($quiz->attempts_count > 0) {
            $this->error('Students have already attempted this quiz — their results would be lost.');

            return self::FAILURE;
        }

        $lesson = $quiz->lesson;
        $quiz->questions()->delete();
        $quiz->delete();

        if ($lesson && $lesson->type === LessonType::Quiz) {
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
        $this->error('Unknown action — use list, save, approve or delete.');

        return self::FAILURE;
    }
}
