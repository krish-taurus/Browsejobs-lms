<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'topic_id' => Topic::factory(),
            'title' => fake()->sentence(3),
            'type' => LessonType::Notes->value,
            'position' => 1,
        ];
    }

    public function codingLab(): self
    {
        return $this->state(['type' => LessonType::CodingLab->value]);
    }
}
