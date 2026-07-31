<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductKind;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds the monetization settings singleton + the default product catalog (PRD
 * §6.17). Self-paced products are created on publish, not seeded. Idempotent.
 */
class MonetizationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            app(EntitlementService::class)->settings(); // creates the settings row from config

            $catalog = [
                ['sku' => 'cv-3pack', 'name' => 'CV generations · 3-pack', 'feature' => 'cv', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.cv.pack_price_paise'), 'grant' => (int) config('monetization.cv.pack_size'), 'period' => null],
                ['sku' => 'voice-3pack', 'name' => 'Voice interviews · 3 sessions', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.voice_mock.pack3_paise'), 'grant' => 3, 'period' => null],
                ['sku' => 'voice-6pack', 'name' => 'Voice interviews · 6 sessions', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.voice_mock.pack6_paise'), 'grant' => 6, 'period' => null],
                ['sku' => 'mentor-extra', 'name' => 'Extra mentor 1:1', 'feature' => 'mentor', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.mentor.extra_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'job-kit', 'name' => 'Interview Kit · one job', 'feature' => 'job_kit', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.job_kit.price_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'job-kit-mentor', 'name' => 'Interview Kit + mentor 1:1 · one job', 'feature' => 'job_kit', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.job_kit.mentor_price_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'career-plus', 'name' => 'Career+ (monthly)', 'feature' => 'career_plus', 'kind' => ProductKind::Subscription, 'price' => (int) config('monetization.career_plus.price_paise'), 'grant' => 0, 'period' => (int) config('monetization.career_plus.period_days')],
            ];

            // The retired single-session sku must not linger purchasable.
            Product::query()->where('tenant_id', $tenant->id)->where('sku', 'voice-single')->update(['active' => false]);

            foreach ($catalog as $p) {
                Product::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $p['sku']],
                    [
                        'name' => $p['name'],
                        'feature' => $p['feature'],
                        'kind' => $p['kind']->value,
                        'price_paise' => $p['price'],
                        'grant_amount' => $p['grant'],
                        'period_days' => $p['period'],
                        'active' => true,
                    ],
                );
            }
        });
    }
}
