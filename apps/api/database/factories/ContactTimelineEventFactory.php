<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContactTimelineEvent;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactTimelineEvent>
 */
class ContactTimelineEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lead_id' => Lead::factory(),
            'type' => ContactTimelineEvent::TYPE_NOTE,
            'actor_id' => null,
            'body' => fake()->sentence(),
            'meta' => null,
            'occurred_at' => now(),
        ];
    }
}
