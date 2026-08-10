<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The recorded masterclass a new lead can watch as a daily "simulated
     * live" showing (so a Monday lead never waits for Saturday). YouTube or
     * direct MP4 URL, set from the CRM Masterclasses page.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('masterclass_video_url', 2000)->nullable()->after('tagline');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('masterclass_video_url');
        });
    }
};
