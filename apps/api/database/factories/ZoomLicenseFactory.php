<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\ZoomLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoomLicense>
 */
class ZoomLicenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'label' => 'License '.fake()->numberBetween(1, 20),
            'zoom_user_id' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }
}
