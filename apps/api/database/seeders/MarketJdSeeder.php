<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\MarketJd;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A small pre-parsed job-market JD corpus (PRD §6.21) so demand trends and the
 * syllabus-recommendation engine are demo-able immediately. Idempotent — keyed
 * on the JD fingerprint. Pre-parsed (skills already set) so the demo doesn't
 * depend on a live AI call.
 */
class MarketJdSeeder extends Seeder
{
    /** @var list<array{role: string, jd: string, skills: list<string>, seniority: string, course_kw: string}> */
    private const SAMPLES = [
        ['role' => 'Data Engineer', 'course_kw' => 'data', 'seniority' => 'mid',
            'jd' => 'Design and operate batch + streaming pipelines. Strong Python and SQL. Experience with Spark, Airflow, dbt and a cloud warehouse (Snowflake/BigQuery) on AWS.',
            'skills' => ['python', 'sql', 'spark', 'airflow', 'dbt', 'snowflake', 'aws']],
        ['role' => 'Data Engineer', 'course_kw' => 'data', 'seniority' => 'junior',
            'jd' => 'Junior Data Engineer to build ETL jobs. Python, SQL, basic AWS, and willingness to learn Airflow and data modeling.',
            'skills' => ['python', 'sql', 'aws', 'airflow', 'data modeling', 'etl']],
        ['role' => 'Analytics Engineer', 'course_kw' => 'data', 'seniority' => 'mid',
            'jd' => 'Own the transformation layer. Advanced SQL, dbt, and BI (Power BI / Tableau). Python a plus.',
            'skills' => ['sql', 'dbt', 'power bi', 'tableau', 'python']],
        ['role' => 'QA Automation Engineer', 'course_kw' => 'test', 'seniority' => 'mid',
            'jd' => 'Build automation frameworks. Selenium, API testing (REST), CI/CD with Jenkins, and Java or Python scripting.',
            'skills' => ['selenium', 'rest', 'api', 'ci/cd', 'jenkins', 'java', 'python']],
        ['role' => 'Java Full Stack Developer', 'course_kw' => 'java', 'seniority' => 'mid',
            'jd' => 'Core Java, Spring Boot, REST APIs, SQL/MySQL, and a modern frontend framework. Docker and CI/CD experience valued.',
            'skills' => ['java', 'spring boot', 'rest', 'api', 'sql', 'mysql', 'docker', 'ci/cd']],
    ];

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                foreach (self::SAMPLES as $sample) {
                    $course = Course::query()
                        ->where('name', 'like', '%'.$sample['course_kw'].'%')
                        ->first();

                    $fingerprint = MarketJd::fingerprintFor($sample['role'], $sample['jd']);

                    MarketJd::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'fingerprint' => $fingerprint],
                        [
                            'course_id' => $course?->id,
                            'source' => 'csv',
                            'role_title' => $sample['role'],
                            'company' => 'Sample Corp',
                            'location' => 'Bengaluru',
                            'seniority' => $sample['seniority'],
                            'raw_jd' => $sample['jd'],
                            'extracted_skills' => $sample['skills'],
                            'quality_score' => 75,
                            'parse_status' => MarketJd::STATUS_PARSED,
                            'parsed_at' => now(),
                            'ingested_at' => now(),
                        ],
                    );
                }
            });
        }
    }
}
