<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GradeStatus;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentGrade>
 */
class AssignmentGradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'submission_id' => AssignmentSubmission::factory(),
            'status' => GradeStatus::Draft->value,
            'score' => 80,
            'max_points' => 100,
            'feedback' => 'Solid work overall.',
            'criteria' => [
                ['criterion' => 'Correctness', 'score' => 45, 'note' => 'Mostly correct.'],
                ['criterion' => 'Code quality', 'score' => 25, 'note' => 'Readable.'],
                ['criterion' => 'Documentation', 'score' => 10, 'note' => 'Add a README.'],
            ],
            'ai_likelihood' => 20,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GradeStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }
}
