<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People whose CV matches a JD but who have no AI interview for the role yet:
 * the shortlist agent sends them the AI screening interview instead of
 * shortlisting on the CV alone, and keeps the list on the run so the client
 * sees who is being screened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_shortlist_runs', function (Blueprint $table) {
            $table->json('awaiting')->nullable()->after('near_misses');
        });
    }

    public function down(): void
    {
        Schema::table('job_shortlist_runs', function (Blueprint $table) {
            $table->dropColumn('awaiting');
        });
    }
};
