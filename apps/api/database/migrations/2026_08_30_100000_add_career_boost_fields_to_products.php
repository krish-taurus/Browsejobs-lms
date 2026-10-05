<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Career Boost packages (candidate request, Aug 2026): a time-boxed bundle
 * sold through the existing Product/purchase pipeline, carrying three more
 * quotas beyond the single feature+grant_amount pair every other product
 * kind uses. Null for every other kind — meaningful only where kind =
 * 'career_boost'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('mock_bonus_amount')->nullable()->after('grant_amount');
            $table->unsignedInteger('job_application_bonus_amount')->nullable()->after('mock_bonus_amount');
            $table->unsignedInteger('wider_market_job_limit')->nullable()->after('job_application_bonus_amount');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['mock_bonus_amount', 'job_application_bonus_amount', 'wider_market_job_limit']);
        });
    }
};
