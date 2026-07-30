<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployerJob> */
final class EmployerJobFactory extends Factory
{
    protected $model = EmployerJob::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_workspace_id' => EmployerWorkspace::factory(),
            'created_by_id' => User::factory(),
            'title' => fake()->randomElement(['Backend Engineer', 'Data Engineer', 'DevOps Engineer', 'Frontend Developer']),
            'description' => fake()->paragraphs(3, true),
            'role_family' => 'engineering',
            'skills' => ['python', 'sql', 'aws'],
            'experience_min_years' => 1,
            'experience_max_years' => 4,
            'locations' => ['Bengaluru'],
            'remote' => false,
            'openings' => 2,
            'status' => EmployerJobStatus::Draft->value,
        ];
    }

    public function published(): self
    {
        return $this->state([
            'status' => EmployerJobStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
