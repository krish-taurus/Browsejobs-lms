<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeePlanStatus;
use App\Enums\FeePlanType;
use App\Models\Batch;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePlan>
 */
class FeePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'batch_id' => Batch::factory(),
            'type' => FeePlanType::Single->value,
            'total_paise' => 3_000_000,
            'discount_paise' => 0,
            'gst_rate_bps' => 1800,
            'currency' => 'INR',
            'status' => FeePlanStatus::Active->value,
        ];
    }
}
