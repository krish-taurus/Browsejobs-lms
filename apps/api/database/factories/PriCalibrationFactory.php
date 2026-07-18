<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriCalibration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriCalibration>
 */
class PriCalibrationFactory extends Factory
{
    protected $model = PriCalibration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weights' => ['mastery' => 0.6, 'engagement' => 0.25, 'attendance' => 0.15],
            'correlations' => ['mastery' => 0.5, 'engagement' => 0.2, 'attendance' => 0.1],
            'cohort_size' => 12,
            'applied' => true,
            'computed_at' => now(),
        ];
    }
}
