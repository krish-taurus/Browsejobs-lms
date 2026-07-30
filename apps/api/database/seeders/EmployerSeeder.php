<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmployerRole;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo employer workspace so the employer portal is demo-able immediately
 * (CLAUDE.md DoD #7). Login: employer@example.com / password.
 */
final class EmployerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $owner = User::query()->where('email', 'employer@example.com')->first()
                ?? User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Priya Sharma',
                    'email' => 'employer@example.com',
                    'user_type' => 'employer',
                ]);

            $workspace = EmployerWorkspace::query()->updateOrCreate(
                ['slug' => 'acme-technologies'],
                [
                    'name' => 'Acme Technologies',
                    'website' => 'https://acme.example.com',
                    'industry' => 'IT Services',
                    'company_size' => '201-1000',
                    'locations' => ['Bengaluru', 'Pune'],
                    'status' => 'active',
                ],
            );

            EmployerMember::query()->updateOrCreate(
                ['employer_workspace_id' => $workspace->id, 'user_id' => $owner->id],
                ['role' => EmployerRole::Owner->value, 'joined_at' => now()],
            );

            $recruiter = User::query()->where('email', 'recruiter@example.com')->first()
                ?? User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Arjun Mehta',
                    'email' => 'recruiter@example.com',
                    'user_type' => 'employer',
                ]);

            EmployerMember::query()->updateOrCreate(
                ['employer_workspace_id' => $workspace->id, 'user_id' => $recruiter->id],
                ['role' => EmployerRole::Recruiter->value, 'joined_at' => now()],
            );
        });
    }
}
