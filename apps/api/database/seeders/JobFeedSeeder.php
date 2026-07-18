<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JobFeedItem;
use App\Models\JobFeedSource;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A default internal job-feed source + a couple of pre-parsed feed items (PRD
 * §6.22) so the "Jobs for You" feed is demo-able immediately. Idempotent.
 */
class JobFeedSeeder extends Seeder
{
    /** @var list<array{title: string, company: string, location: string, skills: list<string>, desc: string}> */
    private const ITEMS = [
        ['title' => 'Data Engineer', 'company' => 'Nimbus Analytics', 'location' => 'Bengaluru',
            'skills' => ['python', 'sql', 'airflow', 'dbt', 'aws'],
            'desc' => 'Build and own batch + streaming pipelines. Python, SQL, Airflow, dbt on AWS. 2+ years.'],
        ['title' => 'Junior Data Engineer', 'company' => 'Kettle Data', 'location' => 'Remote',
            'skills' => ['python', 'sql', 'etl'],
            'desc' => 'Entry-level Data Engineer to build ETL jobs in Python and SQL. Training provided.'],
        ['title' => 'QA Automation Engineer', 'company' => 'Testbase', 'location' => 'Hyderabad',
            'skills' => ['selenium', 'java', 'api', 'ci/cd'],
            'desc' => 'Automation frameworks with Selenium and Java, API testing, CI/CD with Jenkins.'],
    ];

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                $source = JobFeedSource::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'kind' => JobFeedSource::KIND_INTERNAL, 'name' => 'Internal openings'],
                    ['priority' => 10, 'is_active' => true, 'last_synced_at' => now()],
                );

                foreach (self::ITEMS as $item) {
                    $fingerprint = JobFeedItem::fingerprintFor(null, $item['company'], $item['title'], $item['location']);

                    JobFeedItem::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'fingerprint' => $fingerprint],
                        [
                            'job_feed_source_id' => $source->id,
                            'title' => $item['title'],
                            'company' => $item['company'],
                            'location' => $item['location'],
                            'work_mode' => 'hybrid',
                            'description' => $item['desc'],
                            'apply_url' => 'https://example.com/jobs/'.md5($fingerprint),
                            'source_kind' => JobFeedSource::KIND_INTERNAL,
                            'role_title' => $item['title'],
                            'extracted_skills' => $item['skills'],
                            'seniority' => 'mid',
                            'quality_score' => 75,
                            'status' => JobFeedItem::STATUS_ACTIVE,
                            'posted_at' => now()->subDays(2),
                            'expires_at' => now()->addDays(28),
                            'ingested_at' => now(),
                        ],
                    );
                }
            });
        }
    }
}
