<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\FeePlan;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'instalment_id' => Instalment::factory(),
            'razorpay_order_id' => 'order_'.fake()->unique()->bothify('##########'),
            'razorpay_payment_id' => null,
            'amount_paise' => 3_000_000,
            'status' => PaymentStatus::Created->value,
        ];
    }

    public function captured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'razorpay_payment_id' => 'pay_'.fake()->unique()->bothify('##########'),
            'status' => PaymentStatus::Captured->value,
            'captured_at' => now(),
        ]);
    }
}
