<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyllabusRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyllabusRecommendation>
 */
class SyllabusRecommendationFactory extends Factory
{
    protected $model = SyllabusRecommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => SyllabusRecommendation::STATUS_PENDING,
            'source' => SyllabusRecommendation::SOURCE_ON_DEMAND,
            'content_source' => 'ai',
            'summary' => 'Market data suggests adding dbt and Airflow coverage.',
            'items' => [
                ['action' => 'add', 'topic' => 'dbt', 'rationale' => 'In 40% of JDs, absent from the outline.', 'priority' => 'high'],
            ],
            'evidence' => ['market_sample' => 5, 'market_demand' => [], 'interview_topics' => [], 'failure_points' => []],
            'market_sample' => 5,
        ];
    }
}
