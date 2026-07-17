<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeflectionOutcome;
use App\Enums\TicketCategory;
use App\Enums\TutorConfidence;
use App\Models\Tenant;
use App\Models\TicketDeflection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketDeflection>
 */
class TicketDeflectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => User::factory(),
            'category' => TicketCategory::Payments->value,
            'question' => 'When is my next EMI due?',
            'answer' => 'Your next instalment is ₹10,000, due 5 August [C1].',
            'confidence' => TutorConfidence::High->value,
            'cited_chunk_ids' => [],
            'outcome' => DeflectionOutcome::Offered->value,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => [
            'outcome' => DeflectionOutcome::Accepted->value,
            'resolved_at' => now(),
        ]);
    }
}
