<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\MockBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockBlueprint>
 */
class MockBlueprintFactory extends Factory
{
    protected $model = MockBlueprint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'role_title' => 'Software Engineer',
            'competencies' => ['Fundamentals', 'Problem solving', 'Communication'],
            'opening_question' => 'Tell me about a project you are proud of.',
            'is_active' => true,
        ];
    }
}
