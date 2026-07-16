<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiEventStatus;
use App\Enums\AiPurpose;
use App\Models\AiEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiEvent>
 */
class AiEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'purpose' => AiPurpose::General->value,
            'model' => 'claude-sonnet-5',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'cost_micros' => 87_500,
            'latency_ms' => 800,
            'status' => AiEventStatus::Ok->value,
        ];
    }
}
