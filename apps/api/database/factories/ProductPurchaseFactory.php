<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductKind;
use App\Enums\PurchaseStatus;
use App\Models\ProductPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPurchase>
 */
class ProductPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'sku' => 'voice-3pack',
            'feature' => 'voice_mock',
            'kind' => ProductKind::Pack->value,
            'amount_paise' => 59_900,
            'total_paise' => 59_900,
            'status' => PurchaseStatus::Pending->value,
            'razorpay_order_id' => 'order_TEST000001',
        ];
    }

    public function captured(): self
    {
        return $this->state(['status' => PurchaseStatus::Captured->value, 'captured_at' => now()]);
    }
}
