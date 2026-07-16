<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmTask>
 */
class CrmTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lead_id' => Lead::factory(),
            'assigned_to' => User::factory(),
            'title' => fake()->sentence(4),
            'due_at' => now()->addDay(),
            'completed_at' => null,
            'source' => CrmTask::SOURCE_MANUAL,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => now(),
        ]);
    }
}
