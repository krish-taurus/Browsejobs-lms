<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobFeedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobFeedItem>
 */
class JobFeedItemFactory extends Factory
{
    protected $model = JobFeedItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Data Engineer';
        $company = fake()->company();

        return [
            'title' => $title,
            'company' => $company,
            'location' => 'Bengaluru',
            'work_mode' => 'hybrid',
            'description' => 'Build ETL pipelines with Python, SQL, Airflow and dbt on AWS.',
            'apply_url' => 'https://example.com/jobs/'.fake()->unique()->numerify('#####'),
            'source_kind' => 'internal',
            'role_title' => $title,
            'extracted_skills' => ['python', 'sql', 'airflow', 'dbt', 'aws'],
            'seniority' => 'mid',
            'quality_score' => 70,
            'fingerprint' => JobFeedItem::fingerprintFor(null, $company, $title, 'Bengaluru'),
            'status' => JobFeedItem::STATUS_ACTIVE,
            'posted_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
            'ingested_at' => now(),
        ];
    }
}
