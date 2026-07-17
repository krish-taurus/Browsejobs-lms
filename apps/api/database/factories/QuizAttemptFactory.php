<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuizAttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quiz_id' => Quiz::factory(),
            'user_id' => User::factory(),
            'status' => QuizAttemptStatus::Pending->value,
        ];
    }

    public function submitted(int $pct = 80): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QuizAttemptStatus::Submitted->value,
            'score_pct' => $pct,
            'correct_count' => $pct,
            'total_count' => 100,
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
        ]);
    }
}
