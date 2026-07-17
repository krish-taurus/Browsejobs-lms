<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.4 — native mentor & interview scheduling (PRD §6.11). Profiles with
 * expertise tags, recurring weekly availability + dated exceptions (IST
 * minutes-of-day), and sessions (mentoring or placement interviews) with the
 * full lifecycle: booking, Zoom, reminders, no-shows, mentor feedback → PRI,
 * student ratings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id');
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->json('expertise_tags');
            $table->boolean('is_active')->default(true);
            // Busy-sync target once Google credentials arrive (founder input).
            $table->string('google_calendar_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        // Recurring weekly windows, minutes-of-day in IST (0 = Sunday).
        Schema::create('mentor_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('mentor_profile_id')->index();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->timestamps();
        });

        // Dated overrides: whole-day off (null window) or a blocked window.
        Schema::create('mentor_availability_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('mentor_profile_id')->index();
            $table->date('date');
            $table->unsignedSmallInteger('start_minute')->nullable();
            $table->unsignedSmallInteger('end_minute')->nullable();
            $table->timestamps();
        });

        Schema::create('mentor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('mentor_profile_id')->index();
            $table->foreignId('student_id')->index();
            // mentoring | placement_interview — placement reuses this engine.
            $table->string('purpose', 30)->default('mentoring');
            // booked | completed | cancelled | no_show
            $table->string('status', 20)->default('booked')->index();
            // Who failed to appear when status = no_show: student | mentor.
            $table->string('no_show_side', 10)->nullable();
            $table->timestamp('starts_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('zoom_meeting_id')->nullable();
            $table->string('join_url', 500)->nullable();
            $table->string('start_url', 1000)->nullable();
            $table->json('feedback')->nullable();
            $table->unsignedTinyInteger('feedback_score')->nullable();
            $table->unsignedTinyInteger('student_rating')->nullable();
            $table->string('student_comment', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Reminder self-guards (sync-driver safe, like quiz reminders).
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_1h_at')->nullable();
            $table->timestamps();

            $table->index(['mentor_profile_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_sessions');
        Schema::dropIfExists('mentor_availability_exceptions');
        Schema::dropIfExists('mentor_availabilities');
        Schema::dropIfExists('mentor_profiles');
    }
};
