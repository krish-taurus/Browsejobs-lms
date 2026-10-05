<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide default for the "candidate scores well → auto-shortlisted"
 * behaviour requested against every employer JD, not just ones an employer
 * has manually configured an Automation rule for (PRD-E F6 extension). Lives
 * on monetization_settings alongside the other CRM-editable free-tier caps —
 * same one-row-per-tenant, no-deploy-to-change pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->boolean('auto_shortlist_enabled')->default(true)->after('text_practice_enabled');
            $table->unsignedTinyInteger('auto_shortlist_min_score')->default(70)->after('auto_shortlist_enabled');
            $table->unsignedTinyInteger('auto_shortlist_min_cv_match_pct')->default(60)->after('auto_shortlist_min_score');
        });
    }

    public function down(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->dropColumn(['auto_shortlist_enabled', 'auto_shortlist_min_score', 'auto_shortlist_min_cv_match_pct']);
        });
    }
};
