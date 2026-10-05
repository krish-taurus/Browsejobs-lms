<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_hub_items', function (Blueprint $table) {
            // Real YouTube view count, kept in sync from the CRM's already-
            // connected channel (see browsejobs-crm's content-hub:sync-youtube
            // command) — null for anything added by hand, which just sorts
            // last whenever more than one item is competing to be "most-watched".
            $table->unsignedBigInteger('view_count')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('content_hub_items', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
