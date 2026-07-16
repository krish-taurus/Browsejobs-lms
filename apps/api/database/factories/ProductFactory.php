<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductKind;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => 'voice-3pack',
            'name' => 'Voice mock 3-pack',
            'feature' => 'voice_mock',
            'kind' => ProductKind::Pack->value,
            'price_paise' => 59_900,
            'grant_amount' => 3,
            'period_days' => null,
            'source_batch_id' => null,
            'active' => true,
        ];
    }

    public function careerPlus(): self
    {
        return $this->state([
            'sku' => 'career-plus', 'name' => 'Career+', 'feature' => 'career_plus',
            'kind' => ProductKind::Subscription->value, 'price_paise' => 49_900,
            'grant_amount' => 0, 'period_days' => 30,
        ]);
    }
}
