<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EmployerWorkspace> */
final class EmployerWorkspaceFactory extends Factory
{
    protected $model = EmployerWorkspace::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'website' => fake()->url(),
            'industry' => fake()->randomElement(['IT Services', 'SaaS', 'Fintech', 'E-commerce', 'Consulting']),
            'company_size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-1000', '1000+']),
            'locations' => [fake()->city()],
            'status' => 'active',
        ];
    }
}
