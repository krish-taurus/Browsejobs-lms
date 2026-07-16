<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\ActivityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityEvent>
 */
class ActivityEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => ActivityType::Login->value,
            'value' => null,
            'occurred_at' => now(),
        ];
    }
}
