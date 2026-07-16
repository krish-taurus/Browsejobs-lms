<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'course_id' => Course::factory(),
            'name' => fake()->words(2, true),
            'position' => 1,
        ];
    }
}
