<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuizSource;
use App\Enums\QuizStatus;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lesson_id' => Lesson::factory()->state(['type' => 'quiz']),
            'title' => fake()->sentence(3),
            'instructions' => 'Answer all questions before the timer runs out.',
            'time_limit_sec' => 600,
            'pass_pct' => 60,
            'shuffle' => true,
            'status' => QuizStatus::Draft->value,
            'source' => QuizSource::Manual->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QuizStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }
}
