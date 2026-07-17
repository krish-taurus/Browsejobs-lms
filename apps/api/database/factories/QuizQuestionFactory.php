<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quiz_id' => Quiz::factory(),
            'prompt' => fake()->sentence().'?',
            'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_index' => 0,
            'explanation' => 'The first option is correct.',
            'position' => 0,
        ];
    }
}
