<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmployerApplicationStage;
use App\Enums\EntitlementFeature;
use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\Course;
use App\Models\CvProfile;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\PlacementStory;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A demo-able candidate (PRD-E F9/F10/F11): one signed-in identity with
 * applications at different stages, a rising mock trajectory, a
 * part-finished verification checklist and market roles in the feed, so the
 * board and the dashboard are worth looking at the moment the app boots.
 */
final class CandidateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $candidate = User::query()->updateOrCreate(
                ['email' => 'candidate@example.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Meera Iyer',
                    'user_type' => 'student',
                    'is_active' => true,
                ],
            );

            CvProfile::query()->updateOrCreate(
                ['user_id' => $candidate->id],
                [
                    'tenant_id' => $tenant->id,
                    'data' => [
                        'summary' => 'Analytics engineer, three years on warehouse and reporting stacks.',
                        'skills' => ['python', 'sql', 'airflow', 'dbt'],
                        'experience' => [
                            ['company' => 'Northwind Analytics', 'title' => 'Analytics Engineer', 'years' => '2023–2026'],
                        ],
                        'education' => [
                            ['institution' => 'PES University', 'qualification' => 'B.E. Computer Science'],
                        ],
                    ],
                ],
            );

            app(EntitlementService::class)->grantCredits(
                $candidate,
                EntitlementFeature::VoiceMock->value,
                7,
                'demo seed',
            );

            $this->seedVerification($tenant, $candidate);
            $this->seedApplications($tenant, $candidate);
            $this->seedMarketFeed($tenant);
            $this->seedStories($tenant);
        });
    }

    /** Identity verified, employment in review, the rest untouched. */
    private function seedVerification(Tenant $tenant, User $candidate): void
    {
        $states = [
            [VerificationKind::Identity, VerificationStatus::Verified],
            [VerificationKind::Employment, VerificationStatus::Pending],
            [VerificationKind::Education, VerificationStatus::NotStarted],
            [VerificationKind::Documents, VerificationStatus::NotStarted],
        ];

        foreach ($states as [$kind, $status]) {
            CandidateVerificationCheck::query()->updateOrCreate(
                ['user_id' => $candidate->id, 'kind' => $kind->value],
                [
                    'tenant_id' => $tenant->id,
                    'status' => $status->value,
                    'provider' => $status === VerificationStatus::NotStarted ? null : 'manual',
                    'submitted_at' => $status === VerificationStatus::NotStarted ? null : now()->subDays(4),
                    'verified_at' => $status === VerificationStatus::Verified ? now()->subDays(3) : null,
                    'expires_at' => $status === VerificationStatus::Verified ? now()->addYears(2) : null,
                ],
            );
        }
    }

    private function seedApplications(Tenant $tenant, User $candidate): void
    {
        $jobs = EmployerJob::query()->limit(3)->get();

        $plan = [
            [EmployerApplicationStage::L1, [58, 66, 74, 81]],
            [EmployerApplicationStage::Shortlisted, [62, 71]],
            [EmployerApplicationStage::Applied, []],
        ];

        foreach ($jobs as $i => $job) {
            [$stage, $scores] = $plan[$i] ?? [EmployerApplicationStage::Applied, []];

            $blueprint = MockBlueprint::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'employer_job_id' => $job->id],
                [
                    'role_title' => $job->title,
                    'competencies' => array_slice($job->skills ?? [], 0, 4) ?: ['role fit'],
                    'opening_question' => 'Introduce yourself, then tell me why you fit this role.',
                    'is_active' => false,
                ],
            );

            $bestInterviewId = null;
            $best = 0;

            foreach ($scores as $n => $score) {
                $interview = MockInterview::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $candidate->id,
                    'mock_blueprint_id' => $blueprint->id,
                    'mode' => MockInterview::MODE_TEXT,
                    'status' => MockInterview::STATUS_COMPLETED,
                    'overall_score' => $score,
                    'started_at' => now()->subDays(20 - $n * 4),
                    'completed_at' => now()->subDays(20 - $n * 4),
                ]);

                if ($score > $best) {
                    $best = $score;
                    $bestInterviewId = $interview->id;
                }
            }

            EmployerJobApplication::query()->updateOrCreate(
                ['employer_job_id' => $job->id, 'candidate_id' => $candidate->id],
                [
                    'tenant_id' => $tenant->id,
                    'stage' => $stage->value,
                    'mock_score' => $best ?: null,
                    'mock_interview_id' => $bestInterviewId,
                    'mock_attempts' => count($scores),
                    'graded_at' => $best ? now()->subDays(2) : null,
                ],
            );
        }
    }

    /**
     * Consented placement stories for the momentum panel. Marked
     * is_sample = false because these stand in for real rows; the panel
     * filters samples out precisely so demo data never reaches a candidate
     * as somebody's outcome.
     */
    private function seedStories(Tenant $tenant): void
    {
        $course = Course::query()->first();

        if ($course === null) {
            return;
        }

        $stories = [
            ['Rahul Menon', 'Support engineer', 'Data Engineer', 'Northwind Data', 'Three rounds, offer in nine days.'],
            ['Sneha Patil', 'Manual tester', 'Analytics Engineer', 'Kestrel Systems', 'The JD mock was the same shape as the real interview.'],
        ];

        foreach ($stories as $i => [$name, $before, $after, $company, $quote]) {
            PlacementStory::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'student_name' => $name],
                [
                    'course_id' => $course->id,
                    'before_label' => $before,
                    'after_role' => $after,
                    'company_name' => $company,
                    'quote' => $quote,
                    'rounds' => 3,
                    'consent' => true,
                    'is_published' => true,
                    'is_sample' => false,
                    'position' => $i + 1,
                ],
            );
        }
    }

    /** A handful of aggregated market roles so the second segment is real. */
    private function seedMarketFeed(Tenant $tenant): void
    {
        $source = JobFeedSource::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Demo market feed'],
            ['kind' => JobFeedSource::KIND_SCRAPER, 'is_active' => true],
        );

        $roles = [
            ['Senior Analytics Engineer', 'Northwind Data', 'Bengaluru', ['sql', 'dbt', 'snowflake']],
            ['Data Platform Engineer', 'Kestrel Systems', 'Pune', ['python', 'spark', 'aws']],
            ['BI Developer', 'Lumen Retail', 'Remote', ['sql', 'powerbi']],
            ['Machine Learning Engineer', 'Vantage Labs', 'Hyderabad', ['python', 'pytorch']],
        ];

        foreach ($roles as $i => [$title, $company, $location, $skills]) {
            JobFeedItem::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'fingerprint' => JobFeedItem::fingerprintFor(null, $company, $title, $location),
                ],
                [
                    'job_feed_source_id' => $source->id,
                    'title' => $title,
                    'company' => $company,
                    'location' => $location,
                    'work_mode' => $location === 'Remote' ? 'remote' : 'onsite',
                    'description' => "{$title} at {$company}.",
                    'source_kind' => JobFeedSource::KIND_SCRAPER,
                    'role_title' => $title,
                    'extracted_skills' => $skills,
                    'prep_questions' => [
                        ['question' => 'Walk me through a model you own end to end.', 'why' => null, 'source' => 'ai'],
                        ['question' => 'How do you catch a silently wrong metric?', 'why' => null, 'source' => 'ai'],
                    ],
                    'seniority' => $i === 0 ? 'senior' : 'mid',
                    'quality_score' => 80,
                    'status' => JobFeedItem::STATUS_ACTIVE,
                    'posted_at' => now()->subDays($i + 1),
                    'ingested_at' => now(),
                ],
            );
        }
    }
}
