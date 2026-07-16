<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InAppNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InAppNotification>
 */
class InAppNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'url' => null,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => ['read_at' => now()]);
    }
}
