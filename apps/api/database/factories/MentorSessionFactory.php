<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorSession>
 */
class MentorSessionFactory extends Factory
{
    protected $model = MentorSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentor_profile_id' => MentorProfile::factory(),
            'student_id' => User::factory()->state(['user_type' => 'student']),
            'purpose' => MentorSession::PURPOSE_MENTORING,
            'status' => MentorSession::STATUS_BOOKED,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'duration_minutes' => 30,
        ];
    }
}
