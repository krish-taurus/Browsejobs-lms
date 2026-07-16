<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketAuthorType;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ticket_id' => Ticket::factory(),
            'author_id' => null,
            'author_type' => TicketAuthorType::Student->value,
            'body' => 'Here are the details of my issue.',
            'is_internal' => false,
            'channel' => TicketMessage::CHANNEL_PORTAL,
        ];
    }
}
