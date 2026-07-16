<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccessBlockLevel;
use App\Models\AccessBlock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessBlock>
 */
class AccessBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'level' => AccessBlockLevel::Soft->value,
            'reason' => 'Overdue registration instalment',
            'blocked_at' => now(),
            'lifted_at' => null,
        ];
    }

    public function hard(): static
    {
        return $this->state(fn (array $attributes): array => ['level' => AccessBlockLevel::Hard->value]);
    }

    public function lifted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lifted_at' => now(), 'lifted_reason' => 'Payment received',
        ]);
    }
}
