<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Models\FeePlan;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'direction' => LedgerDirection::Debit->value,
            'amount_paise' => 1_000_000,
            'description' => 'Instalment due',
            'occurred_at' => now(),
        ];
    }
}
