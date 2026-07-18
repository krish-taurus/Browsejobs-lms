<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SalaryBenchmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryBenchmark>
 */
class SalaryBenchmarkFactory extends Factory
{
    protected $model = SalaryBenchmark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_title' => 'Data Engineer',
            'city' => 'Bengaluru',
            'experience_band' => 'fresher',
            'p25_ctc_paise' => 6_00_000 * 100,
            'p50_ctc_paise' => 8_00_000 * 100,
            'p75_ctc_paise' => 11_00_000 * 100,
            'source' => 'Placement team dataset',
            'effective_on' => now()->toDateString(),
        ];
    }
}
