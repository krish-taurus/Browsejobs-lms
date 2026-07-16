<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'module_id' => Module::factory(),
            'name' => fake()->words(2, true),
            'position' => 1,
        ];
    }
}
