<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\SalaryBenchmark;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A small curated salary-benchmark dataset (PRD §6.21) so offer-evaluation
 * guidance is demo-able. Figures are illustrative annual CTC (LPA), stored in
 * paise. Idempotent per (role, city, band).
 */
class SalaryBenchmarkSeeder extends Seeder
{
    /** @var list<array{role: string, city: string, band: string, p25: float, p50: float, p75: float, kw: string}> */
    private const ROWS = [
        ['role' => 'Data Engineer', 'city' => 'Bengaluru', 'band' => 'fresher', 'p25' => 6, 'p50' => 8, 'p75' => 11, 'kw' => 'data'],
        ['role' => 'Data Engineer', 'city' => 'Bengaluru', 'band' => '1-3', 'p25' => 10, 'p50' => 14, 'p75' => 20, 'kw' => 'data'],
        ['role' => 'Data Engineer', 'city' => 'Hyderabad', 'band' => 'fresher', 'p25' => 5.5, 'p50' => 7.5, 'p75' => 10, 'kw' => 'data'],
        ['role' => 'QA Automation Engineer', 'city' => 'Bengaluru', 'band' => 'fresher', 'p25' => 4.5, 'p50' => 6, 'p75' => 8.5, 'kw' => 'test'],
        ['role' => 'Java Full Stack Developer', 'city' => 'Bengaluru', 'band' => '1-3', 'p25' => 8, 'p50' => 12, 'p75' => 18, 'kw' => 'java'],
    ];

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($tenant): void {
                foreach (self::ROWS as $row) {
                    $course = Course::query()->where('name', 'like', '%'.$row['kw'].'%')->first();

                    SalaryBenchmark::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'role_title' => $row['role'],
                            'city' => $row['city'],
                            'experience_band' => $row['band'],
                        ],
                        [
                            'course_id' => $course?->id,
                            'p25_ctc_paise' => (int) round($row['p25'] * 100_000 * 100),
                            'p50_ctc_paise' => (int) round($row['p50'] * 100_000 * 100),
                            'p75_ctc_paise' => (int) round($row['p75'] * 100_000 * 100),
                            'source' => 'Illustrative dataset',
                            'effective_on' => now()->toDateString(),
                        ],
                    );
                }
            });
        }
    }
}
