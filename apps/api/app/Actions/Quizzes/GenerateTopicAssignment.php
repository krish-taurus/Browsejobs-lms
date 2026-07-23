<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Enums\AiPurpose;
use App\Enums\LessonType;
use App\Enums\QuizSource;
use App\Enums\QuizStatus;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * One-click MCQ assignment for a chapter (ADR 0049/0050). Assignments are
 * generated FROM the chapter's study notes — never before them — so questions
 * only test what students were actually given to read. Each call creates a NEW
 * numbered assignment (a chapter can have several; each quiz id is the unique
 * code sent to candidates). Drafts until a trainer approves.
 */
final readonly class GenerateTopicAssignment
{
    /** Notes passed to the model are capped to keep the prompt inside budget. */
    private const NOTES_LIMIT = 12000;

    public function __construct(private AiGateway $gateway) {}

    /** The chapter's notes, if any exist with content. */
    public static function notesFor(Topic $topic): ?LessonNote
    {
        $lesson = $topic->lessons()->where('type', LessonType::Notes->value)->first();
        if ($lesson === null) {
            return null;
        }

        $note = LessonNote::query()->where('lesson_id', $lesson->id)->first();

        return $note?->hasNotes() ? $note : null;
    }

    /**
     * @return array{quiz: Quiz, written: int}
     */
    public function handle(Topic $topic, User $actor, LessonNote $note, int $count = 8): array
    {
        return app(TenantContext::class)->run($topic->tenant, function () use ($topic, $actor, $note, $count): array {
            $module = $topic->module;

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::QuizGen,
                'quiz_from_notes',
                1,
                [
                    'course' => $module?->course?->name ?? 'the course',
                    'module' => $module?->name ?? 'the module',
                    'chapter' => $topic->name,
                    'notes' => mb_substr((string) $note->notes, 0, self::NOTES_LIMIT),
                    'count' => (string) $count,
                ],
                ['max_tokens' => (int) config('ai.tutor.max_answer_tokens', 700) * 3],
            );

            $items = $this->parse($result->text);
            if ($items === []) {
                return ['quiz' => new Quiz, 'written' => 0];
            }

            return DB::transaction(function () use ($topic, $items): array {
                $sequence = $topic->lessons()->where('type', LessonType::Quiz->value)->count() + 1;
                $lesson = Lesson::query()->create([
                    'tenant_id' => $topic->tenant_id,
                    'topic_id' => $topic->id,
                    'title' => "{$topic->name} — Assignment {$sequence}",
                    'type' => LessonType::Quiz->value,
                    'position' => $topic->lessons()->count(),
                ]);

                $quiz = Quiz::query()->create([
                    'tenant_id' => $topic->tenant_id,
                    'lesson_id' => $lesson->id,
                    'title' => $lesson->title,
                    'source' => QuizSource::Ai->value,
                    'status' => QuizStatus::Draft->value,
                ]);

                $position = 0;
                foreach ($items as $item) {
                    QuizQuestion::query()->create([
                        'tenant_id' => $quiz->tenant_id,
                        'quiz_id' => $quiz->id,
                        'prompt' => $item['prompt'],
                        'options' => $item['options'],
                        'correct_index' => $item['correct_index'],
                        'explanation' => $item['explanation'],
                        'position' => $position++,
                    ]);
                }

                return ['quiz' => $quiz, 'written' => $position];
            });
        });
    }

    /**
     * Same tolerant MCQ parsing as GenerateQuiz: markdown fences and prose
     * around the JSON are fine; malformed items are dropped.
     *
     * @return list<array{prompt: string, options: list<string>, correct_index: int, explanation: string}>
     */
    private function parse(string $text): array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $row) {
            if (! is_array($row) || ! isset($row['prompt'], $row['options'], $row['correct_index'])) {
                continue;
            }

            $options = array_values(array_filter((array) $row['options'], 'is_string'));
            $correct = (int) $row['correct_index'];

            if (count($options) < 2 || $correct < 0 || $correct >= count($options)) {
                continue;
            }

            $items[] = [
                'prompt' => trim((string) $row['prompt']),
                'options' => $options,
                'correct_index' => $correct,
                'explanation' => trim((string) ($row['explanation'] ?? '')),
            ];
        }

        return $items;
    }
}
