<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobFeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobFeedSource>
 */
class JobFeedSourceFactory extends Factory
{
    protected $model = JobFeedSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Internal openings',
            'kind' => JobFeedSource::KIND_INTERNAL,
            'priority' => 10,
            'is_active' => true,
        ];
    }
}
