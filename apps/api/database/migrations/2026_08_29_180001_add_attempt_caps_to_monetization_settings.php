<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempt caps for the AI interview flows (candidate request, Aug 2026):
 * course mocks and employer-JD mocks are separate systems with their own
 * free-tier ceilings, plus a free-tier cap on how many jobs a candidate can
 * apply to. All three are CRM-editable (crm.browsejobs.ai writes this row
 * directly — see the monetization_settings grant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->unsignedInteger('general_mock_attempts_per_blueprint')->default(3)->after('voice_included_self_paced');
            $table->unsignedInteger('employer_mock_attempts_per_job')->default(3)->after('general_mock_attempts_per_blueprint');
            $table->unsignedInteger('free_job_application_limit')->default(5)->after('employer_mock_attempts_per_job');
        });
    }

    public function down(): void
    {
        Schema::table('monetization_settings', function (Blueprint $table): void {
            $table->dropColumn(['general_mock_attempts_per_blueprint', 'employer_mock_attempts_per_job', 'free_job_application_limit']);
        });
    }
};
