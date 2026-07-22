<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small context line under a review author (e.g. "Local Guide · 17 reviews"),
 * shown on the course-page reviews so real Google/JustDial reviews read as
 * genuine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('author_meta')->nullable()->after('author_name');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('author_meta');
        });
    }
};
