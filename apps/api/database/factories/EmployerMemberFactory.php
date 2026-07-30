<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployerRole;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployerMember> */
final class EmployerMemberFactory extends Factory
{
    protected $model = EmployerMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_workspace_id' => EmployerWorkspace::factory(),
            'user_id' => User::factory(),
            'role' => EmployerRole::Recruiter->value,
            'joined_at' => now(),
        ];
    }

    public function owner(): self
    {
        return $this->state(['role' => EmployerRole::Owner->value]);
    }

    public function hiringManager(): self
    {
        return $this->state(['role' => EmployerRole::HiringManager->value]);
    }
}
