<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TestimonialStatus;
use App\Enums\VoucherType;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds the review-reward voucher and one demo pending testimonial so the voucher
 * manager + moderation queue are demo-able immediately (PRD §5 Stage 3). No
 * public reviews are fabricated — approval is what creates a `reviews` row.
 * Idempotent.
 */
class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            Voucher::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Testimonial reward'],
                [
                    'type' => VoucherType::Flat->value,
                    'value' => 200_000,            // ₹2,000 off registration
                    'valid_days' => 30,
                    'per_user_limit' => 1,
                    'allow_stacking' => false,
                    'is_review_reward' => true,
                    'active' => true,
                ],
            );

            $student = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_type', 'student')
                ->first();

            if ($student === null || Testimonial::query()->where('tenant_id', $tenant->id)->exists()) {
                return;
            }

            Testimonial::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $student->id,
                'course_slug' => 'python-fullstack',
                'rating' => 5,
                'body' => 'The bootcamp was direct and honest. I finally understood what interviews actually ask, and the mock rounds felt real.',
                'consent_publish' => true,
                'status' => TestimonialStatus::Pending->value,
            ]);
        });
    }
}
