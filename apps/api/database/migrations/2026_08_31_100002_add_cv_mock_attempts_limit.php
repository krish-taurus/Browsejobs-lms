<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-tier cap on the AI Readiness Interview — its own counter, same
 * pattern as employer_mock_attempts_per_job and general_mock_attempts_per_blueprint,
 * deliberately separate from both (a 15-question CV interview is a bigger
 * investment than either, so it gets its own smaller default). CRM-editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cv_mock_attempts_limit')->default(2)->after('auto_shortlist_min_cv_match_pct');
        });
    }

    public function down(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->dropColumn('cv_mock_attempts_limit');
        });
    }
};
