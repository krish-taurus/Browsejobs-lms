<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Models\Tenant;
use App\Models\TicketRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRoute>
 */
class TicketRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category' => TicketCategory::Technical->value,
            'label' => 'Technical',
            'routing' => TicketRoute::ROUTING_TEAM,
            'support_team_id' => null,
            'first_response_minutes' => 240,
            'resolution_minutes' => 2880,
            'round_robin_pointer' => null,
            'active' => true,
        ];
    }
}
