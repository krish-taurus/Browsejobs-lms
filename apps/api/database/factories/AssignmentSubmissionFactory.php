<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'link' => 'https://github.com/example/repo',
            'status' => AssignmentSubmissionStatus::Submitted->value,
            'submitted_at' => now(),
        ];
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => AssignmentSubmissionStatus::Graded->value]);
    }
}
