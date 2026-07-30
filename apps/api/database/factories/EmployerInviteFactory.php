<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmployerRole;
use App\Models\EmployerInvite;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EmployerInvite> */
final class EmployerInviteFactory extends Factory
{
    protected $model = EmployerInvite::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'employer_workspace_id' => EmployerWorkspace::factory(),
            'invited_by_id' => User::factory(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'role' => EmployerRole::Recruiter->value,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
