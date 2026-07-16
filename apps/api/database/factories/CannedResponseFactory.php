<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CannedResponse;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedResponse>
 */
class CannedResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'Ask for a screenshot',
            'body' => 'Thanks for reaching out. Could you share a screenshot of the error you see?',
            'category' => null,
            'active' => true,
        ];
    }
}
