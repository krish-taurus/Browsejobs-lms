<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Enums\LessonType;
use App\Enums\QuizStatus;
use App\Models\BatchMember;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the MCQ spine (P3.4) demo-able: an approved quiz on a module's `quiz` lesson
 * (creating the lesson if the seeded curriculum lacks one), plus one submitted sample
 * attempt so the quiz→mastery blend shows on the Coach Panel.
 */
class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            // Attach the quiz to an existing quiz lesson, or create one under the first topic.
            $lesson = Lesson::query()->where('type', LessonType::Quiz->value)->orderBy('id')->first();
            if ($lesson === null) {
                $topic = Topic::query()->orderBy('id')->first();
                if ($topic === null) {
                    return;
                }
                $lesson = Lesson::query()->create([
                    'tenant_id' => $tenant->id,
                    'topic_id' => $topic->id,
                    'title' => 'Module 1 Check',
                    'type' => LessonType::Quiz->value,
                    'position' => 99,
                ]);
            }

            $quiz = Quiz::query()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                ['tenant_id' => $tenant->id, 'title' => 'Module 1 Check', 'status' => QuizStatus::Approved->value, 'approved_at' => now(), 'time_limit_sec' => 300],
            );

            if ($quiz->questions()->count() === 0) {
                foreach ($this->questions() as $i => $q) {
                    QuizQuestion::query()->create([
                        'tenant_id' => $tenant->id,
                        'quiz_id' => $quiz->id,
                        'prompt' => $q['prompt'],
                        'options' => $q['options'],
                        'correct_index' => $q['correct_index'],
                        'explanation' => $q['explanation'],
                        'position' => $i,
                    ]);
                }
            }

            // One submitted sample attempt so mastery blends visibly.
            $member = BatchMember::query()->whereIn('status', ['enrolled', 'reserved', 'payment_pending', 'completed'])->first();
            if ($member !== null) {
                $attempt = QuizAttempt::query()->firstOrCreate(
                    ['quiz_id' => $quiz->id, 'user_id' => $member->user_id],
                    ['tenant_id' => $tenant->id, 'status' => 'in_progress', 'started_at' => now(), 'deadline_at' => now()->addMinutes(5)],
                );

                if ($attempt->status->value !== 'submitted') {
                    $questions = $quiz->questions()->get();
                    $answers = $questions->take(2)->mapWithKeys(fn (QuizQuestion $q) => [(string) $q->id => $q->correct_index])->all();
                    app(SubmitQuizAttempt::class)->handle($attempt, $answers, ['tab_blurs' => 1, 'paste_count' => 0, 'duration_sec' => 140]);
                }
            }
        });
    }

    /**
     * @return list<array{prompt: string, options: list<string>, correct_index: int, explanation: string}>
     */
    private function questions(): array
    {
        return [
            ['prompt' => 'Which keyword defines a function in Python?', 'options' => ['func', 'def', 'function', 'lambda'], 'correct_index' => 1, 'explanation' => '`def` starts a function definition.'],
            ['prompt' => 'What does len([1,2,3]) return?', 'options' => ['2', '3', '4', 'Error'], 'correct_index' => 1, 'explanation' => 'The list has three elements.'],
            ['prompt' => 'Which type is immutable?', 'options' => ['list', 'dict', 'tuple', 'set'], 'correct_index' => 2, 'explanation' => 'Tuples cannot be changed after creation.'],
        ];
    }
}
