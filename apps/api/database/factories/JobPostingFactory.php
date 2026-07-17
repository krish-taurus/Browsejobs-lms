<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Data Engineer',
            'company' => fake()->company(),
            'location' => 'Bengaluru',
            'work_mode' => 'hybrid',
            'description' => 'Build and maintain batch pipelines on a modern data stack.',
            'status' => JobPosting::STATUS_OPEN,
        ];
    }
}
