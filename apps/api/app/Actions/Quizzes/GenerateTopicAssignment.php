<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * One-click MCQ assignment for a teaching day (ADR 0049): ensures the topic has
 * a `quiz` lesson + quiz shell, then drafts day-focused questions through the
 * existing GenerateQuiz pipeline. The quiz stays draft until a trainer approves
 * it, and the assignment paper (PDF) is rendered or uploaded separately.
 */
final readonly class GenerateTopicAssignment
{
    public function __construct(private GenerateQuiz $generate) {}

    /**
     * @return array{quiz: Quiz, written: int}
     */
    public function handle(Topic $topic, User $actor, int $count = 8): array
    {
        $quiz = app(TenantContext::class)->run($topic->tenant, function () use ($topic): Quiz {
            $lesson = $topic->lessons()->where('type', LessonType::Quiz->value)->first()
                ?? Lesson::query()->create([
                    'tenant_id' => $topic->tenant_id,
                    'topic_id' => $topic->id,
                    'title' => $topic->name.' — assignment',
                    'type' => LessonType::Quiz->value,
                    'position' => $topic->lessons()->count(),
                ]);

            return Quiz::query()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                ['tenant_id' => $topic->tenant_id, 'title' => $topic->name.' — assignment'],
            );
        });

        $written = $this->generate->handle($quiz, $actor, $count);

        return ['quiz' => $quiz->refresh(), 'written' => $written];
    }
}
