<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Vapi voice provider records the call by default once requested; this
 * is only where we keep the URL it hands back at end-of-call. Employers
 * open it from the application, not the candidate — a recording is the
 * employer's evidence, not something a candidate re-plays for themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table) {
            $table->string('recording_url')->nullable()->after('join_url');
        });
    }

    public function down(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table) {
            $table->dropColumn('recording_url');
        });
    }
};
