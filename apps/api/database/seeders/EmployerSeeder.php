<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmployerJobStatus;
use App\Enums\EmployerRole;
use App\Enums\JdMockStatus;
use App\Models\EmployerJob;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
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

            // Demo JD with a ready JD-specific mock (PRD-E F2).
            $job = EmployerJob::query()->updateOrCreate(
                ['employer_workspace_id' => $workspace->id, 'title' => 'Data Engineer'],
                [
                    'created_by_id' => $recruiter->id,
                    'description' => 'Own our lakehouse pipelines end to end: Python + SQL transformations orchestrated in Airflow on AWS. You will design incremental models, monitor data quality, and partner with analytics.',
                    'role_family' => 'engineering',
                    'skills' => ['python', 'sql', 'airflow', 'aws', 'data modeling'],
                    'experience_min_years' => 2,
                    'experience_max_years' => 5,
                    'locations' => ['Bengaluru'],
                    'remote' => false,
                    'ctc_min_paise' => 120000000,
                    'ctc_max_paise' => 180000000,
                    'ctc_visible' => true,
                    'openings' => 2,
                    'status' => EmployerJobStatus::Published->value,
                    'published_at' => now(),
                ],
            );

            JdMock::query()->updateOrCreate(
                ['employer_job_id' => $job->id, 'version' => 1],
                [
                    'status' => JdMockStatus::Ready->value,
                    'source' => 'ai',
                    'questions' => [
                        ['id' => 1, 'text' => 'Walk me through an incremental pipeline you built: source, transformation logic, and how you handled late-arriving data.', 'skill' => 'data modeling', 'type' => 'scenario', 'weight' => 2],
                        ['id' => 2, 'text' => 'Your Airflow DAG has been failing intermittently at 3am. How do you debug it?', 'skill' => 'airflow', 'type' => 'scenario', 'weight' => 2],
                        ['id' => 3, 'text' => 'When would you choose a window function over a self-join in SQL? Give a concrete example.', 'skill' => 'sql', 'type' => 'technical', 'weight' => 1],
                        ['id' => 4, 'text' => 'How do you make a Python data job idempotent so a retry never double-writes?', 'skill' => 'python', 'type' => 'technical', 'weight' => 2],
                        ['id' => 5, 'text' => 'Which AWS services would you pick for a daily 50GB batch pipeline, and why those over alternatives?', 'skill' => 'aws', 'type' => 'technical', 'weight' => 1],
                        ['id' => 6, 'text' => 'Tell me about a time a stakeholder disputed your numbers. What did you do?', 'skill' => 'ownership', 'type' => 'behavioral', 'weight' => 1],
                    ],
                    'rubric' => [
                        'dimensions' => [
                            ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 40, 'criteria' => 'Concrete, correct detail on Python/SQL/Airflow/AWS.'],
                            ['key' => 'problem_solving', 'label' => 'Problem solving', 'weight' => 25, 'criteria' => 'Structured debugging and design trade-offs.'],
                            ['key' => 'experience_evidence', 'label' => 'Evidence of experience', 'weight' => 20, 'criteria' => 'Real pipelines with outcomes, not generalities.'],
                            ['key' => 'communication', 'label' => 'Communication', 'weight' => 15, 'criteria' => 'Clear, concise verbal explanations.'],
                        ],
                    ],
                    'generated_at' => now(),
                ],
            );
        });
    }
}
