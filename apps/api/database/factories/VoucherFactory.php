<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => null,
            'name' => 'Testimonial reward',
            'type' => VoucherType::Flat->value,
            'value' => 200_000,          // ₹2,000
            'max_discount_paise' => null,
            'valid_days' => 30,
            'valid_until' => null,
            'course_id' => null,
            'usage_limit' => null,
            'per_user_limit' => 1,
            'allow_stacking' => false,
            'is_review_reward' => false,
            'active' => true,
        ];
    }

    public function reviewReward(): self
    {
        return $this->state(['is_review_reward' => true]);
    }

    public function percent(int $bps, ?int $capPaise = null): self
    {
        return $this->state([
            'type' => VoucherType::Percent->value,
            'value' => $bps,
            'max_discount_paise' => $capPaise,
        ]);
    }
}
