<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScoreSnapshot;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreSnapshot>
 */
class ScoreSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'captured_on' => now()->toDateString(),
            'pri' => 55,
            'engagement' => 50,
            'risk_dropout' => 30,
            'risk_placement' => 40,
        ];
    }
}
