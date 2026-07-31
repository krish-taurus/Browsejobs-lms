<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks interviews taken in the browser interview room (video-call UI over the
 * text session). Text-mode rows were indistinguishable from room sessions, so
 * staff reporting could not tell virtual interviews apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->boolean('is_room')->default(false)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->dropColumn('is_room');
        });
    }
};
