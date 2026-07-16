<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentScore;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentScore>
 */
class StudentScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'engagement' => 50,
            'risk_dropout' => 30,
            'risk_placement' => 40,
            'pri' => 55,
            'streak_days' => 3,
            'mastery' => [],
            'next_action' => ['key' => 'next_topic', 'title' => 'Complete your next topic', 'detail' => '', 'href' => null],
            'computed_at' => now(),
        ];
    }
}
