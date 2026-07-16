<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TutorConversation;
use App\Models\TutorMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorMessage>
 */
class TutorMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'conversation_id' => TutorConversation::factory(),
            'student_id' => User::factory(),
            'role' => TutorMessage::ROLE_STUDENT,
            'body' => fake()->sentence(),
            'cited_chunk_ids' => null,
            'confidence' => null,
            'escalated' => false,
            'ticket_id' => null,
            'question_fingerprint' => null,
            'ai_event_id' => null,
        ];
    }

    public function tutor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TutorMessage::ROLE_TUTOR,
            'confidence' => 'high',
        ]);
    }
}
