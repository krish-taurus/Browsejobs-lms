<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mentor-area rework (ADR 0043):
 * - `kind` distinguishes a normal class from a weekly batch mentoring /
 *   doubt-clearing session — both run on the same live-class engine.
 * - `host_user_id` is an explicit per-session host (the mentor for a
 *   mentoring session); when null the module/lead trainer hosts as before.
 * - `zoom_license_id` records which pool license hosts the meeting, which is
 *   also the rotation lock: a license with an overlapping claimed session is
 *   busy and the next free one is picked instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->string('kind', 20)->default('class')->after('topic_id');
            $table->foreignId('host_user_id')->nullable()->after('kind')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('zoom_license_id')->nullable()->after('zoom_meeting_id')
                ->constrained('zoom_licenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('zoom_license_id');
            $table->dropConstrainedForeignId('host_user_id');
            $table->dropColumn('kind');
        });
    }
};
