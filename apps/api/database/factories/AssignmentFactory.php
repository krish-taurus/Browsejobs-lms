<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rubric = [
            ['criterion' => 'Correctness', 'max_points' => 50],
            ['criterion' => 'Code quality', 'max_points' => 30],
            ['criterion' => 'Documentation', 'max_points' => 20],
        ];

        return [
            'tenant_id' => Tenant::factory(),
            'lesson_id' => Lesson::factory()->state(['type' => 'assignment']),
            'title' => fake()->sentence(3),
            'instructions' => 'Build the feature and submit your repo link.',
            'rubric' => $rubric,
            'max_points' => array_sum(array_column($rubric, 'max_points')),
            'allow_link' => true,
            'allow_file' => true,
        ];
    }
}
