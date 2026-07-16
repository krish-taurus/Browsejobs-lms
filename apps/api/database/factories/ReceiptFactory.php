<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'payment_id' => Payment::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'number' => 'RCPT-'.fake()->unique()->numberBetween(1000, 9999),
            'taxable_paise' => 847_458,
            'cgst_paise' => 76_271,
            'sgst_paise' => 76_271,
            'total_paise' => 1_000_000,
            'status' => Receipt::STATUS_PENDING,
        ];
    }
}
