<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'user_id' => User::factory()->state(['user_type' => 'student']),
            'status' => JobApplication::STATUS_APPLIED,
        ];
    }
}
