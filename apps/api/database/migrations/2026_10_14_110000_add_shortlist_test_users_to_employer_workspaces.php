<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shortlist test mode for a workspace: while this list of BrowseJobs student
 * ids is set, the shortlist agent considers only them (no other student, no
 * client database or Naukri candidate), so the WhatsApp interview flow can be
 * tried end to end without messaging anyone else. Null = normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_workspaces', function (Blueprint $table) {
            $table->json('shortlist_test_user_ids')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employer_workspaces', function (Blueprint $table) {
            $table->dropColumn('shortlist_test_user_ids');
        });
    }
};
