<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the post-class study nudge was sent for a session (PRD §6 daily loop).
 * Set once the class's material is ready and the cohort has been nudged — the
 * dedup so `class:wrapup` never sends twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->timestamp('wrapped_up_at')->nullable()->after('reminder_token');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropColumn('wrapped_up_at');
        });
    }
};
