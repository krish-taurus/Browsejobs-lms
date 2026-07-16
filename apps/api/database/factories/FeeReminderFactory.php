<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeeReminder;
use App\Models\Instalment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeReminder>
 */
class FeeReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'instalment_id' => Instalment::factory(),
            'rung' => 'due',
            'channel' => 'log',
            'sent_at' => now(),
        ];
    }
}
