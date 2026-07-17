<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SyllabusStatus;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Syllabus>
 */
class SyllabusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3).' — Syllabus',
            'content' => "## Overview\n\n".fake()->paragraph()."\n\n## Learning outcomes\n\n- ".fake()->sentence()."\n- ".fake()->sentence(),
            'status' => SyllabusStatus::Draft->value,
            'curriculum_hash' => null,
            'is_stale' => false,
            'render_status' => Syllabus::RENDER_PENDING,
            'version' => 1,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SyllabusStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rendered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'render_status' => Syllabus::RENDER_RENDERED,
            'storage_path' => 'syllabus/1/example.html',
        ]);
    }
}
