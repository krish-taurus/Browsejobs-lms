<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When nobody clears a JD's minimum match, the shortlist agent keeps the
 * closest few so the client sees who almost made it (and can lower the bar)
 * instead of an empty list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_shortlist_runs', function (Blueprint $table) {
            $table->json('near_misses')->nullable()->after('shortlisted_count');
        });
    }

    public function down(): void
    {
        Schema::table('job_shortlist_runs', function (Blueprint $table) {
            $table->dropColumn('near_misses');
        });
    }
};
