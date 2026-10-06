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
                ['sku' => 'voice-single', 'name' => 'Voice mock · single session', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.voice_mock.single_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'voice-3pack', 'name' => 'Voice mock · 3-pack', 'feature' => 'voice_mock', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.voice_mock.pack_price_paise'), 'grant' => (int) config('monetization.voice_mock.pack_size'), 'period' => null],
                ['sku' => 'mentor-extra', 'name' => 'Extra mentor 1:1', 'feature' => 'mentor', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.mentor.extra_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'job-kit', 'name' => 'Interview Kit · one job', 'feature' => 'job_kit', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.job_kit.price_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'job-kit-mentor', 'name' => 'Interview Kit + mentor 1:1 · one job', 'feature' => 'job_kit', 'kind' => ProductKind::Pack, 'price' => (int) config('monetization.job_kit.mentor_price_paise'), 'grant' => 1, 'period' => null],
                ['sku' => 'career-plus', 'name' => 'Career+ (monthly)', 'feature' => 'career_plus', 'kind' => ProductKind::Subscription, 'price' => (int) config('monetization.career_plus.price_paise'), 'grant' => 0, 'period' => (int) config('monetization.career_plus.period_days')],
                ['sku' => 'career-boost-199', 'name' => 'Career Boost — 30 Days', 'feature' => 'cv', 'kind' => ProductKind::CareerBoost, 'price' => 19900, 'grant' => 5, 'period' => 30, 'mock' => 10, 'jobs' => 10, 'wider' => 50],
                ['sku' => 'career-boost-299', 'name' => 'Career Boost Plus — 30 Days', 'feature' => 'cv', 'kind' => ProductKind::CareerBoost, 'price' => 29900, 'grant' => 10, 'period' => 30, 'mock' => 20, 'jobs' => 20, 'wider' => 100],
                ['sku' => 'career-boost-499', 'name' => 'Career Boost Pro — 30 Days', 'feature' => 'cv', 'kind' => ProductKind::CareerBoost, 'price' => 49900, 'grant' => 20, 'period' => 30, 'mock' => 40, 'jobs' => 40, 'wider' => 200],
            ];

            foreach ($catalog as $p) {
                $attributes = [
                    'name' => $p['name'],
                    'feature' => $p['feature'],
                    'kind' => $p['kind']->value,
                    'price_paise' => $p['price'],
                    'grant_amount' => $p['grant'],
                    'period_days' => $p['period'],
                    'active' => true,
                ];
                if (isset($p['mock'])) {
                    $attributes['mock_bonus_amount'] = $p['mock'];
                    $attributes['job_application_bonus_amount'] = $p['jobs'];
                    $attributes['wider_market_job_limit'] = $p['wider'];
                }
                Product::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $p['sku']],
                    $attributes,
                );
            }
        });
    }
}
