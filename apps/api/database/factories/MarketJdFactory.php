<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MarketJd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketJd>
 */
class MarketJdFactory extends Factory
{
    protected $model = MarketJd::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = 'Data Engineer';
        $jd = 'We are hiring a Data Engineer to build ETL pipelines with Python, SQL, Spark and Airflow on AWS.';

        return [
            'source' => 'manual',
            'role_title' => $role,
            'company' => fake()->company(),
            'location' => 'Bengaluru',
            'seniority' => 'mid',
            'raw_jd' => $jd,
            'fingerprint' => MarketJd::fingerprintFor($role, $jd),
            'extracted_skills' => ['python', 'sql', 'spark', 'airflow', 'aws'],
            'quality_score' => 70,
            'parse_status' => MarketJd::STATUS_PARSED,
            'parsed_at' => now(),
            'ingested_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'extracted_skills' => null,
            'parse_status' => MarketJd::STATUS_PENDING,
            'parsed_at' => null,
        ]);
    }
}
