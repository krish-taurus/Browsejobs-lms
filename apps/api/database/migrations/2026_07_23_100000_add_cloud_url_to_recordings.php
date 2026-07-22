<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recordings now stream from Zoom Cloud instead of being imported into our storage:
 * store the Zoom play URL and its passcode. `storage_path` stays for any legacy
 * (already-imported) rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table): void {
            $table->text('play_url')->nullable()->after('storage_path');
            $table->string('passcode')->nullable()->after('play_url');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table): void {
            $table->dropColumn(['play_url', 'passcode']);
        });
    }
};
