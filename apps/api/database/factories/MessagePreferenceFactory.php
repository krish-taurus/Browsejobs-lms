<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Models\MessagePreference;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessagePreference>
 */
class MessagePreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'preferred_channel' => MessageChannel::WhatsApp->value,
            'marketing_opt_in' => false,
        ];
    }
}
