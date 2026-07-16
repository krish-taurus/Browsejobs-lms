<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'course_id' => Course::factory(),
            'number' => 'B-'.fake()->unique()->numberBetween(100000, 999999),
            'type' => BatchType::Paid->value,
            'capacity' => null,
            'status' => 'active',
        ];
    }
}
