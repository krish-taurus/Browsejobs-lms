<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherIssueStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoucherIssue>
 */
class VoucherIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'voucher_id' => Voucher::factory(),
            'user_id' => User::factory(),
            'testimonial_id' => null,
            'code' => strtoupper(Str::random(10)),
            'status' => VoucherIssueStatus::Issued->value,
            'discount_paise' => null,
            'fee_plan_id' => null,
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'applied_at' => null,
        ];
    }
}
