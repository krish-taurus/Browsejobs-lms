<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'provider' => 'razorpay',
            'event' => 'payment.captured',
            'event_id' => fake()->unique()->uuid(),
            'signature_valid' => true,
            'payload' => [],
            'processed_at' => now(),
        ];
    }
}
