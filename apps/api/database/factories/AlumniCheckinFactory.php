<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AlumniCheckin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlumniCheckin>
 */
class AlumniCheckinFactory extends Factory
{
    protected $model = AlumniCheckin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'milestone' => AlumniCheckin::MILESTONE_6M,
            'status' => AlumniCheckin::STATUS_SCHEDULED,
            'scheduled_for' => now()->toDateString(),
        ];
    }
}
