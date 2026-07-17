<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3.6 (PRD §6.16): the points ledger behind batch leaderboards, per-tenant
     * weight settings, and milestone badges. `points_events` is append-only and
     * idempotent per (user, source, source_key) — the anti-gaming spine: points
     * exist only when a verified server-side flow wrote them.
     */
    public function up(): void
    {
        Schema::create('points_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attendance_points')->default(20);
            $table->unsignedInteger('punctuality_points')->default(5);
            $table->unsignedInteger('quiz_points')->default(30);
            $table->unsignedInteger('lab_points')->default(25);
            $table->unsignedInteger('streak_day_points')->default(10);
            $table->unsignedInteger('mock_points')->default(40); // reserved for P4
            $table->unsignedInteger('daily_cap')->default(150);
            $table->unsignedTinyInteger('attendance_min_pct')->default(60);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('points_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source'); // PointsSource enum
            // What earned it, e.g. "session:12", "quiz:5", "day:2026-07-17".
            $table->string('source_key');
            $table->unsignedInteger('points');
            $table->date('awarded_on');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'source', 'source_key'], 'points_events_once');
            $table->index(['tenant_id', 'batch_id', 'awarded_on']);
            $table->index(['tenant_id', 'user_id', 'awarded_on']);
        });

        Schema::create('student_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge');
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge']);
            $table->index('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // PRD §6.16: top-10 shown by name with opt-out available.
            $table->boolean('leaderboard_opt_out')->default(false)->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('leaderboard_opt_out');
        });
        Schema::dropIfExists('student_badges');
        Schema::dropIfExists('points_events');
        Schema::dropIfExists('points_settings');
    }
};
