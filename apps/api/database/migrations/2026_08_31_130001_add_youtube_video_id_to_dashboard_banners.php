<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the CRM's "Import latest YouTube videos" bulk-import action be
 * idempotent — re-running it updates the same banner rows (refreshed
 * thumbnail/title if the video changed) instead of creating duplicates
 * every time. Null for any banner added by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_banners', function (Blueprint $table): void {
            $table->string('youtube_video_id')->nullable()->unique()->after('link_label');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_banners', function (Blueprint $table): void {
            $table->dropColumn('youtube_video_id');
        });
    }
};
