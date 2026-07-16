<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConversionNudge;
use App\Models\FeePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversionNudge>
 */
class ConversionNudgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'rung' => 'day-1',
            'channel' => 'log',
            'sent_at' => now(),
        ];
    }
}
