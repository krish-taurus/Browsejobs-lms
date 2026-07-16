<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InstalmentStatus;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instalment>
 */
class InstalmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'seq' => 1,
            'amount_paise' => 3_000_000,
            'due_on' => now()->toDateString(),
            'status' => InstalmentStatus::Pending->value,
        ];
    }
}
