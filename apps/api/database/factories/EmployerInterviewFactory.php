<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployerInterviewRound;
use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerInterview;
use App\Models\EmployerJobApplication;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployerInterview> */
final class EmployerInterviewFactory extends Factory
{
    protected $model = EmployerInterview::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_job_application_id' => EmployerJobApplication::factory(),
            'round' => EmployerInterviewRound::L1->value,
            'status' => EmployerInterviewStatus::Invited->value,
            'question_set' => [
                ['id' => 1, 'text' => 'Explain a pipeline you built.', 'skill' => 'python', 'type' => 'scenario', 'weight' => 1],
                ['id' => 2, 'text' => 'How do you tune a slow SQL query?', 'skill' => 'sql', 'type' => 'technical', 'weight' => 1],
            ],
            'rubric' => [
                'dimensions' => [
                    ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 60, 'criteria' => 'Depth.'],
                    ['key' => 'communication', 'label' => 'Communication', 'weight' => 40, 'criteria' => 'Clarity.'],
                ],
            ],
            'invited_at' => now(),
            'expires_at' => now()->addHours(48),
        ];
    }
}
