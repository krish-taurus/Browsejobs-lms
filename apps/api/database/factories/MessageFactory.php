<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'direction' => MessageDirection::Out->value,
            'channel' => MessageChannel::WhatsApp->value,
            'template_key' => 'class_reminder',
            'category' => MessageCategory::Utility->value,
            'recipient' => fake()->e164PhoneNumber(),
            'body' => 'Your class starts soon.',
            'status' => MessageStatus::Sent->value,
            'sent_at' => now(),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => MessageDirection::In->value,
            'template_key' => null,
            'status' => MessageStatus::Delivered->value,
        ]);
    }
}
