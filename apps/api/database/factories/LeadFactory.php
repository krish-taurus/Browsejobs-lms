<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+9198'.fake()->numerify('########');

        return [
            'tenant_id' => Tenant::factory(),
            'lead_type' => fake()->randomElement(Lead::TYPES),
            'name' => fake()->name(),
            'phone' => $phone,
            'phone_normalized' => preg_replace('/\D+/', '', $phone),
            'email' => fake()->unique()->safeEmail(),
            'course_slug' => fake()->randomElement(['data-engineering', 'devops-cloud', 'data-analytics']),
            'utm_source' => fake()->randomElement(['meta', 'google', 'organic', null]),
            'utm_medium' => fake()->randomElement(['cpc', 'social', null]),
            'utm_campaign' => fake()->optional()->slug(2),
            'page' => '/courses',
            'ip' => fake()->ipv4(),
            'consented_at' => now(),
            'consent_version' => 'v1',
            'score' => 0,
        ];
    }
}
