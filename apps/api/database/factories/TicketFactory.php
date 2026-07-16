<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'reference' => 'BJ-'.strtoupper(Str::random(6)),
            'student_id' => User::factory(),
            'category' => TicketCategory::Technical->value,
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'subject' => 'I cannot access the recordings',
            'first_response_due_at' => now()->addHours(4),
            'resolution_due_at' => now()->addHours(48),
        ];
    }

    public function resolved(): self
    {
        return $this->state(['status' => TicketStatus::Resolved->value, 'resolved_at' => now()]);
    }
}
