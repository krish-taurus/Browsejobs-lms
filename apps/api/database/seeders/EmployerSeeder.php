<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BatchMemberStatus;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerInterviewRound;
use App\Enums\EmployerInterviewStatus;
use App\Enums\EmployerJobStatus;
use App\Enums\EmployerRole;
use App\Enums\JdMockStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvProfile;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\JdMock;
use App\Models\StudentScore;
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

            $mock = JdMock::query()->updateOrCreate(
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

            $this->seedApplications($tenant, $job, $mock);
            $this->seedTrainedTalent($tenant);
        });
    }

    /**
     * Trained BrowseJobs students who have not applied yet — the talent
     * pool an employer can source from (PRD-E F7). Each carries the
     * evidence only this platform has: a built CV, a readiness index,
     * and a completed programme.
     */
    private function seedTrainedTalent(Tenant $tenant): void
    {
        $course = Course::query()->where('name', 'like', '%Data Engineering%')->first()
            ?? Course::query()->first();

        $batch = $course !== null
            ? Batch::query()->where('course_id', $course->id)->first()
            : null;

        /** @var list<array{name: string, email: string, skills: list<string>, pri: int}> */
        $talent = [
            ['name' => 'Aditya Rao', 'email' => 'aditya.rao@example.com', 'skills' => ['python', 'sql', 'airflow', 'aws', 'dbt', 'spark'], 'pri' => 88],
            ['name' => 'Fatima Sheikh', 'email' => 'fatima.sheikh@example.com', 'skills' => ['python', 'sql', 'aws', 'data modeling'], 'pri' => 81],
            ['name' => 'Nikhil Kulkarni', 'email' => 'nikhil.kulkarni@example.com', 'skills' => ['python', 'sql', 'airflow'], 'pri' => 74],
            ['name' => 'Pooja Deshmukh', 'email' => 'pooja.deshmukh@example.com', 'skills' => ['sql', 'aws'], 'pri' => 63],
        ];

        foreach ($talent as $entry) {
            $student = User::query()->where('email', $entry['email'])->first()
                ?? User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $entry['name'],
                    'email' => $entry['email'],
                    'user_type' => 'student',
                ]);

            CvProfile::query()->updateOrCreate(
                ['user_id' => $student->id],
                [
                    'tenant_id' => $tenant->id,
                    'data' => [
                        'skills' => $entry['skills'],
                        'summary' => 'Completed the BrowseJobs programme with a graded project portfolio.',
                    ],
                ],
            );

            StudentScore::query()->updateOrCreate(
                ['user_id' => $student->id],
                ['tenant_id' => $tenant->id, 'pri' => $entry['pri'], 'computed_at' => now()],
            );

            if ($batch !== null) {
                BatchMember::query()->updateOrCreate(
                    ['batch_id' => $batch->id, 'user_id' => $student->id],
                    ['tenant_id' => $tenant->id, 'status' => BatchMemberStatus::Completed->value],
                );
            }
        }
    }

    /**
     * A pipeline with real shape so the employer dashboard is demo-able the
     * moment it is opened (CLAUDE.md DoD #7) — graded applicants ranked above
     * ungraded, candidates spread across stages, and one graded L1 round so the
     * evidence view has something to show.
     */
    private function seedApplications(Tenant $tenant, EmployerJob $job, JdMock $mock): void
    {
        /** @var list<array{name: string, email: string, score: int|null, stage: EmployerApplicationStage, days: int}> */
        $roster = [
            ['name' => 'Ananya Iyer', 'email' => 'ananya.iyer@example.com', 'score' => 91, 'stage' => EmployerApplicationStage::L1, 'days' => 6],
            ['name' => 'Rahul Verma', 'email' => 'rahul.verma@example.com', 'score' => 84, 'stage' => EmployerApplicationStage::Shortlisted, 'days' => 5],
            ['name' => 'Sneha Nair', 'email' => 'sneha.nair@example.com', 'score' => 78, 'stage' => EmployerApplicationStage::Graded, 'days' => 3],
            ['name' => 'Vikram Reddy', 'email' => 'vikram.reddy@example.com', 'score' => 72, 'stage' => EmployerApplicationStage::Graded, 'days' => 2],
            ['name' => 'Meera Joshi', 'email' => 'meera.joshi@example.com', 'score' => 64, 'stage' => EmployerApplicationStage::Graded, 'days' => 2],
            ['name' => 'Karthik Menon', 'email' => 'karthik.menon@example.com', 'score' => null, 'stage' => EmployerApplicationStage::Applied, 'days' => 1],
            ['name' => 'Divya Sharma', 'email' => 'divya.sharma@example.com', 'score' => null, 'stage' => EmployerApplicationStage::Applied, 'days' => 1],
        ];

        foreach ($roster as $entry) {
            $candidate = User::query()->where('email', $entry['email'])->first()
                ?? User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $entry['name'],
                    'email' => $entry['email'],
                    'user_type' => 'student',
                ]);

            $application = EmployerJobApplication::query()->updateOrCreate(
                ['employer_job_id' => $job->id, 'candidate_id' => $candidate->id],
                [
                    'jd_mock_id' => $mock->id,
                    'mock_score' => $entry['score'],
                    'graded_at' => $entry['score'] === null ? null : now()->subDays($entry['days']),
                    'stage' => $entry['stage']->value,
                    'created_at' => now()->subDays($entry['days']),
                ],
            );

            if ($application->transitions()->doesntExist()) {
                $application->transitions()->create([
                    'from_stage' => null,
                    'to_stage' => EmployerApplicationStage::Applied->value,
                    'actor_type' => 'user',
                    'actor_id' => $candidate->id,
                    'occurred_at' => now()->subDays($entry['days']),
                ]);
            }
        }

        // The top candidate has a graded L1 round, so the evidence view shows
        // per-dimension scores and a recruiter summary immediately.
        $top = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->whereHas('candidate', fn ($query) => $query->where('email', 'ananya.iyer@example.com'))
            ->first();

        if ($top !== null) {
            EmployerInterview::query()->updateOrCreate(
                ['employer_job_application_id' => $top->id, 'round' => EmployerInterviewRound::L1->value],
                [
                    'status' => EmployerInterviewStatus::Graded->value,
                    'question_set' => $mock->questions,
                    'rubric' => $mock->rubric,
                    'answers' => [
                        ['question_id' => 1, 'answer' => 'At Zeta I rebuilt the orders pipeline as an incremental dbt model keyed on updated_at with a 48-hour lookback window, so late-arriving rows were reprocessed without a full refresh. Runtime went from 40 minutes to under 4.'],
                        ['question_id' => 2, 'answer' => 'First I check whether the failure is the DAG or the source: task duration and log timestamps in the Airflow UI, then whether the upstream extract landed. Intermittent 3am failures are usually a race with the source export, so I add a sensor with a timeout rather than a fixed delay.'],
                    ],
                    'dimension_scores' => [
                        'technical_depth' => 88,
                        'problem_solving' => 84,
                        'experience_evidence' => 90,
                        'communication' => 76,
                    ],
                    'overall_score' => 86,
                    'grading_summary' => 'Strongest evidence is the incremental pipeline rebuild — specific mechanism, specific outcome, and she named the trade-off she accepted. Debugging answer was structured and started from the right question. Communication is concise but occasionally skips context a non-technical stakeholder would need. Positive hire signal for a mid-level data engineering role.',
                    'grading_source' => 'ai',
                    'invited_at' => now()->subDays(4),
                    'expires_at' => now()->addDays(1),
                    'started_at' => now()->subDays(4),
                    'submitted_at' => now()->subDays(4),
                    'graded_at' => now()->subDays(4),
                ],
            );
        }
    }
}
