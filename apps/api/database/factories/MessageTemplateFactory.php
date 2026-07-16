<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->unique()->slug(2),
            'channel' => MessageChannel::WhatsApp->value,
            'category' => MessageCategory::Utility->value,
            'name' => null,
            'subject' => null,
            'body' => 'Hi {{name}}, this is a message. {{link}}',
            'locale' => 'en',
            'active' => true,
        ];
    }
}
