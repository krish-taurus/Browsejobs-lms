<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The moment the candidate pressed "End interview". The room closes at once
     * and the scorecard is built in the background; until it is, the interview
     * is `in_progress` with `ended_at` set (no more answers, no resume).
     */
    public function up(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->timestamp('ended_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->dropColumn('ended_at');
        });
    }
};
