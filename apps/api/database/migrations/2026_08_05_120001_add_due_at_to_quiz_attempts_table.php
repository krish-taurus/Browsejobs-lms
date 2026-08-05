<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separate "finish it by this date" from "your timer runs out at".
 *
 * `deadline_at` is the server-authoritative clock for a sitting: MyQuizController
 * overwrites it with now + time_limit_sec the moment a student opens the quiz. So
 * when quiz:assign parked the batch's due date there, it was wiped on first open
 * and the student could no longer see when the test was actually due. The due date
 * now has its own column and the timer keeps `deadline_at` to itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('status');
        });

        // Attempts already handed out carry their due date in deadline_at — but
        // only while untouched; once opened that value is a spent timer.
        DB::table('quiz_attempts')
            ->where('status', 'pending')
            ->whereNotNull('deadline_at')
            ->update(['due_at' => DB::raw('deadline_at')]);
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
