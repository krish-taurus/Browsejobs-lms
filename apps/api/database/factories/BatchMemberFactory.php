<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchMember>
 */
class BatchMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'batch_id' => Batch::factory(),
            'user_id' => User::factory(),
            'status' => BatchMemberStatus::Reserved->value,
            'enrolled_at' => null,
        ];
    }

    public function enrolled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchMemberStatus::Enrolled->value,
            'enrolled_at' => now(),
        ]);
    }
}
