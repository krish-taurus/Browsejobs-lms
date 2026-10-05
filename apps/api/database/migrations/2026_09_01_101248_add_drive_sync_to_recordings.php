<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a self-hosted class recording has been pushed to the
 * team's Google Drive (CRM File Manager, batch-numbered folders). Written
 * by the CRM's recordings:sync-to-drive command, not by the LMS itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('storage_path');
            $table->string('drive_link')->nullable()->after('drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['drive_file_id', 'drive_link']);
        });
    }
};
