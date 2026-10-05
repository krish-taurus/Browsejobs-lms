<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The three starting Career Boost tiers (candidate request, Aug 2026). Every
 * number here is CRM-editable afterwards — this migration only seeds a
 * starting point, it never re-runs to "reset" them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('products')->insert([
            [
                'tenant_id' => 1,
                'sku' => 'career-boost-199',
                'name' => 'Career Boost — 30 Days',
                'feature' => 'cv',
                'kind' => 'career_boost',
                'price_paise' => 19_900,
                'grant_amount' => 5,
                'period_days' => 30,
                'mock_bonus_amount' => 10,
                'job_application_bonus_amount' => 10,
                'wider_market_job_limit' => 50,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => 1,
                'sku' => 'career-boost-299',
                'name' => 'Career Boost Plus — 30 Days',
                'feature' => 'cv',
                'kind' => 'career_boost',
                'price_paise' => 29_900,
                'grant_amount' => 10,
                'period_days' => 30,
                'mock_bonus_amount' => 20,
                'job_application_bonus_amount' => 20,
                'wider_market_job_limit' => 100,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => 1,
                'sku' => 'career-boost-499',
                'name' => 'Career Boost Pro — 30 Days',
                'feature' => 'cv',
                'kind' => 'career_boost',
                'price_paise' => 49_900,
                'grant_amount' => 20,
                'period_days' => 30,
                'mock_bonus_amount' => 40,
                'job_application_bonus_amount' => 40,
                'wider_market_job_limit' => 200,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('sku', ['career-boost-199', 'career-boost-299', 'career-boost-499'])->delete();
    }
};
