<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-tier cap on the "Wider Market" (scraped/external) jobs shown on Jobs
 * for You — candidate request (Aug 2026): only show these once a candidate
 * has a CV on file (there's nothing to match against otherwise), capped at a
 * flat number. CRM-editable, same settings screen as the other caps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->unsignedInteger('wider_market_job_limit')->default(20)->after('free_job_application_limit');
        });
    }

    public function down(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->dropColumn('wider_market_job_limit');
        });
    }
};
