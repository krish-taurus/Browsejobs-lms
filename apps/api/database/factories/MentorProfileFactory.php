<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorProfile>
 */
class MentorProfileFactory extends Factory
{
    protected $model = MentorProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['user_type' => 'staff']),
            'headline' => 'Senior Data Engineer · 8 yrs',
            'expertise_tags' => ['sql', 'python'],
            'is_active' => true,
        ];
    }
}
