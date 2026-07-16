<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TutorConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorConversation>
 */
class TutorConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => User::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(3),
            'last_message_at' => now(),
        ];
    }
}
