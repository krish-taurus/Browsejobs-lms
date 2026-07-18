<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Advice\GenerateAlumniCheckins;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\CvProfile;
use App\Models\HiringPartnerFeedback;
use App\Models\JobApplication;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\JobPosting;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A coherent, login-ready demo so staging presents the WHOLE journey (P4.9 demo
 * pass). Turns the existing "star" active student (already given learning
 * telemetry by ScoringSeeder — so their Coach Panel is populated) into a named,
 * OTP-loginable hero and fills the placement side: CV skills, a completed mock,
 * a matched-and-applied feed job. Adds a placed alumnus so the alumni check-in,
 * hiring-partner feedback, and Proof Engine surfaces are non-empty.
 *
 * Runs LAST (after curriculum, batches, feed, scoring). Idempotent; degrades
 * without breaking `db:seed` if a prerequisite is missing.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $this->hero($tenant);
            $this->alumnus($tenant);
        });
    }

    private function hero(Tenant $tenant): void
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $member = BatchMember::query()
            ->whereIn('status', $occupying)
            ->with('batch:id,course_id')
            ->first();

        $hero = $member !== null ? User::query()->find($member->user_id) : null;
        if ($hero === null) {
            return;
        }

        // A known identity so staging can OTP-login to the fully-populated portal.
        $hero->forceFill([
            'name' => 'Asha Rao (demo)',
            'phone' => '+919000000001',
            'email' => 'asha.demo@browsejobs.ai',
            'telemetry_consent_at' => now(),
            'consent_version' => (string) config('dpdp.consent_version', 'v1'),
        ])->save();

        // CV facts — skills chosen to match the seeded job feed (high "Jobs for You").
        CvProfile::query()->updateOrCreate(
            ['user_id' => $hero->id],
            ['tenant_id' => $tenant->id, 'data' => array_merge(CvProfile::EMPTY, [
                'summary' => 'Aspiring Data Engineer — SQL, Python, and pipeline tooling.',
                'skills' => ['Python', 'SQL', 'Airflow', 'dbt'],
            ])],
        );

        // A completed mock so PRI carries interview signal and the mock history shows.
        $blueprint = MockBlueprint::query()->where('course_id', $member->batch?->course_id)->first();
        if ($blueprint !== null) {
            MockInterview::query()->firstOrCreate(
                ['user_id' => $hero->id, 'mock_blueprint_id' => $blueprint->id, 'status' => MockInterview::STATUS_COMPLETED],
                [
                    'tenant_id' => $tenant->id, 'mode' => 'text', 'overall_score' => 72,
                    'scorecard' => ['strong_moments' => ['Clear SQL reasoning'], 'weak_moments' => ['System design depth']],
                    'scorecard_source' => 'fallback', 'started_at' => now()->subDays(3), 'completed_at' => now()->subDays(3),
                ],
            );
        }

        // A matched feed job the hero has applied to → Jobs for You + tracker populated.
        $item = JobFeedItem::query()->where('status', JobFeedItem::STATUS_ACTIVE)->orderByDesc('id')->first();
        if ($item !== null) {
            JobApplication::query()->firstOrCreate(
                ['user_id' => $hero->id, 'job_feed_item_id' => $item->id],
                ['tenant_id' => $tenant->id, 'status' => JobApplication::STATUS_APPLIED],
            );
            JobFeedSave::query()->updateOrCreate(
                ['user_id' => $hero->id, 'job_feed_item_id' => $item->id],
                ['tenant_id' => $tenant->id, 'state' => JobFeedSave::STATE_APPLIED],
            );
        }
    }

    private function alumnus(Tenant $tenant): void
    {
        $alumnus = User::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '+919000000002'],
            [
                'name' => 'Ravi Kumar (demo alumnus)',
                'email' => 'ravi.demo@browsejobs.ai',
                'user_type' => 'student',
                'telemetry_consent_at' => now(),
                'consent_version' => (string) config('dpdp.consent_version', 'v1'),
            ],
        );

        $posting = JobPosting::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Data Engineer', 'company' => 'Nimbus Analytics'],
            [
                'location' => 'Bengaluru', 'work_mode' => 'hybrid',
                'description' => 'Build and own batch + streaming pipelines with Python, SQL, Airflow and dbt.',
                'status' => JobPosting::STATUS_OPEN,
            ],
        );

        // Placed 7 months ago → the 6-month alumni check-in is due.
        $application = JobApplication::query()->firstOrCreate(
            ['user_id' => $alumnus->id, 'job_posting_id' => $posting->id],
            [
                'tenant_id' => $tenant->id,
                'status' => JobApplication::STATUS_PLACED,
                'offer_role' => 'Data Engineer',
                'offer_ctc_paise' => 9_00_000 * 100,
                'offer_accepted_at' => now()->subMonths(7),
                'placed_at' => now()->subMonths(7),
            ],
        );

        // Schedule the due 6/12-month check-ins (idempotent).
        app(GenerateAlumniCheckins::class)->handle();

        // A hiring-partner feedback request awaiting the company's response.
        HiringPartnerFeedback::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'job_application_id' => $application->id],
            [
                'company' => 'Nimbus Analytics', 'role_title' => 'Data Engineer',
                'token' => HiringPartnerFeedback::newToken(),
                'status' => HiringPartnerFeedback::STATUS_REQUESTED,
            ],
        );
    }
}
