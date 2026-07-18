<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPDP telemetry consent at signup (PRD §10 — launch-readiness audit gap #7).
 * Records WHEN and WHICH policy version a student agreed to, including the
 * activity-monitoring telemetry disclosure. Nullable for pre-existing accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('telemetry_consent_at')->nullable()->after('anonymized_at');
            $table->string('consent_version')->nullable()->after('telemetry_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telemetry_consent_at', 'consent_version']);
        });
    }
};
