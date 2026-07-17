<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'recipient_id' => User::factory(),
            'type' => ReportType::WeeklyStudent->value,
            'title' => 'Your week',
            'narrative' => fake()->paragraphs(2, true),
            'data' => ['mastery' => [], 'next_action' => ['title' => 'Complete your next topic']],
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
        ];
    }

    public function trainerBrief(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ReportType::TrainerBrief->value,
            'title' => 'Pre-class brief',
            'period_start' => null,
            'period_end' => null,
        ]);
    }

    public function counselorDigest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ReportType::CounselorDigest->value,
            'title' => 'Daily risk digest',
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ]);
    }
}
