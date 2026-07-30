<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployerApplicationStage;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployerJobApplication> */
final class EmployerJobApplicationFactory extends Factory
{
    protected $model = EmployerJobApplication::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_job_id' => EmployerJob::factory(),
            'candidate_id' => User::factory(),
            'stage' => EmployerApplicationStage::Applied->value,
        ];
    }

    public function graded(int $score = 78): self
    {
        return $this->state([
            'stage' => EmployerApplicationStage::Graded->value,
            'mock_score' => $score,
            'graded_at' => now(),
        ]);
    }
}
