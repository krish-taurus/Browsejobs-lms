<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends PRD-E F6 automation with a CV-match gate alongside the existing
 * interview-score one, and marks the rows a JD gets automatically on
 * publish (SeedDefaultAutomationRule) as distinct from ones an employer
 * authored by hand — the Automation tab uses this to label and to avoid
 * re-seeding a duplicate default if the job is ever republished.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_automation_rules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('min_cv_match_pct')->nullable()->after('min_score');
            $table->boolean('is_default')->default(false)->after('enabled');
        });

        Schema::table('employer_automation_runs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cv_match_seen')->nullable()->after('score_seen');
        });

        Schema::table('employer_job_applications', function (Blueprint $table): void {
            $table->unsignedTinyInteger('cv_match_pct')->nullable()->after('mock_score');
        });
    }

    public function down(): void
    {
        Schema::table('employer_job_applications', function (Blueprint $table): void {
            $table->dropColumn('cv_match_pct');
        });

        Schema::table('employer_automation_runs', function (Blueprint $table): void {
            $table->dropColumn('cv_match_seen');
        });

        Schema::table('employer_automation_rules', function (Blueprint $table): void {
            $table->dropColumn(['min_cv_match_pct', 'is_default']);
        });
    }
};
