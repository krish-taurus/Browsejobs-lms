<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;
use Database\Seeders\SalaryBenchmarkSeeder;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('serves the public salary dataset in paise', function () {
    Tenant::factory()->create();
    $this->seed(SalaryBenchmarkSeeder::class);

    $rows = getJson('/api/v1/salaries')->assertOk()->json('data.rows');

    expect(count($rows))->toBeGreaterThan(3)
        ->and($rows[0])->toHaveKeys(['role', 'city', 'band', 'p25_paise', 'p50_paise', 'p75_paise'])
        ->and(collect($rows)->firstWhere('role', 'Data Engineer')['p50_paise'])->toBeInt();
});

it('scores a pasted resume deterministically with actionable checks', function () {
    $resume = implode("\n", [
        'Asha K — asha@example.com — +91 9876543210',
        'Experience', '- Built ETL pipelines processing 2M rows daily in Spark',
        '- Reduced warehouse cost by 30% by partition tuning',
        '- Automated deployment with Docker and CI/CD across 4 services',
        'Education: BE Computer Science', 'Skills: Python, SQL, Spark, Airflow, Kafka, dbt, AWS Glue, Databricks',
        'Projects', '- Designed a streaming pipeline handling 50k events per minute with exactly once delivery',
        '- Implemented a dimensional warehouse model with slowly changing dimensions for finance reporting',
        '- Migrated 12 legacy cron jobs to Airflow DAGs with retries alerting and SLA tracking dashboards',
        '- Delivered a data quality framework running 200 checks nightly across bronze silver gold layers',
        '- Improved query latency from 40 seconds to 3 seconds by clustering and materialised view design',
        '- Integrated CDC ingestion from Postgres into the lakehouse using Debezium and structured streaming',
    ]);

    $data = postJson('/api/v1/ats-check', ['text' => $resume])->assertOk()->json('data');

    expect($data['score'])->toBe(100)
        ->and($data['checks'])->toHaveCount(5)
        ->and(collect($data['checks'])->every(fn ($c) => $c['pass']))->toBeTrue();
});

it('flags a weak resume with failing checks and hints', function () {
    $data = postJson('/api/v1/ats-check', [
        'text' => str_repeat('I am a hardworking person seeking opportunities in software. ', 3),
    ])->assertOk()->json('data');

    expect($data['score'])->toBeLessThan(40)
        ->and(collect($data['checks'])->firstWhere('label', 'Contact details parseable')['pass'])->toBeFalse()
        ->and(collect($data['checks'])->first()['hint'])->toBeString();
});

it('rejects an empty or oversized paste', function () {
    postJson('/api/v1/ats-check', ['text' => 'too short'])->assertStatus(422);
    postJson('/api/v1/ats-check', ['text' => str_repeat('a', 20001)])->assertStatus(422);
});

it('gates the career report behind lead registration and returns an unbiased analysis', function () {
    $tenant = Tenant::factory()->create(['domain' => 'acme.test']);

    $resume = "Experience\n- Built ETL pipelines in Spark and Python processing 2M rows\n- Airflow DAGs with SQL warehouse models\nSkills: Python, SQL, Spark, Airflow, AWS\nEducation: BE";

    // Without registration fields the analysis is refused.
    \Pest\Laravel\postJson('http://acme.test/api/v1/career-report', ['text' => $resume])->assertStatus(422);

    $data = \Pest\Laravel\postJson('http://acme.test/api/v1/career-report', [
        'name' => 'Asha', 'phone' => '+919876543210', 'consent' => true, 'text' => $resume,
    ])->assertOk()->json('data');

    expect(Lead::withoutGlobalScopes()->where('page', '/career-report')->count())->toBe(1)
        ->and($data['summary']['best_path'])->toBe('Data Engineering')
        ->and($data['paths'][0]['fit_pct'])->toBeGreaterThan(60)
        ->and(collect($data['paths'])->pluck('path'))->toContain('ML / AI Engineering') // paths we don't teach are scored too
        ->and(collect($data['paths'])->firstWhere('taught_here', false))->not->toBeNull()
        ->and($data['focus'])->not->toBeEmpty()
        ->and($data['ats']['score'])->toBeInt();
});
