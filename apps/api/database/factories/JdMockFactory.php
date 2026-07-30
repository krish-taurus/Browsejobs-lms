<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JdMockStatus;
use App\Models\EmployerJob;
use App\Models\JdMock;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JdMock> */
final class JdMockFactory extends Factory
{
    protected $model = JdMock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_job_id' => EmployerJob::factory(),
            'version' => 1,
            'status' => JdMockStatus::Pending->value,
        ];
    }

    public function ready(): self
    {
        return $this->state([
            'status' => JdMockStatus::Ready->value,
            'source' => 'ai',
            'questions' => [
                ['id' => 1, 'text' => 'Explain indexing trade-offs in MySQL.', 'skill' => 'sql', 'type' => 'technical', 'weight' => 2],
                ['id' => 2, 'text' => 'Walk through a pipeline you built.', 'skill' => 'python', 'type' => 'scenario', 'weight' => 1],
            ],
            'rubric' => [
                'dimensions' => [
                    ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 50, 'criteria' => 'Concrete detail.'],
                    ['key' => 'problem_solving', 'label' => 'Problem solving', 'weight' => 30, 'criteria' => 'Structured approach.'],
                    ['key' => 'communication', 'label' => 'Communication', 'weight' => 20, 'criteria' => 'Clear explanations.'],
                ],
            ],
            'generated_at' => now(),
        ]);
    }
}
