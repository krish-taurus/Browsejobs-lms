<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            // Both optional: a celebration created before this migration, or
            // one nobody bothered adding media to, still displays fine with
            // text only wherever it's read.
            $table->string('photo_path')->nullable()->after('company');
            $table->string('video_url')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'video_url']);
        });
    }
};
