<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-round placement interviews on top of the existing mentor-session engine.
 *
 * A mentoring booking is confirmed the moment a student picks a slot. A hiring
 * interview is not: the student *applies* for a slot, and the interviewer
 * approves it or moves it. That is the whole reason for `approval_status` —
 * `status` already means "did the session happen", and overloading it would
 * make a pending application indistinguishable from a booked one.
 *
 * `interview_round` on the profile says which round a person takes (Tech Mentor
 * screens at round 1, Tech Manager decides at round 2); on the session it says
 * which round this booking IS. Round 2 only opens once round 1 came back
 * cleared, which is what `outcome` records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table): void {
            // Which interview round this person conducts. Null = mentoring only.
            $table->unsignedTinyInteger('interview_round')->nullable()->after('is_active');
        });

        Schema::table('mentor_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('interview_round')->nullable()->after('purpose');

            // Null for ordinary mentoring, which stays instant-confirm.
            $table->string('approval_status', 20)->nullable()->after('interview_round');

            // Round 1's verdict. Gates whether the student may apply for round 2.
            $table->string('outcome', 20)->nullable()->after('approval_status');

            $table->foreignId('reviewed_by')->nullable()->after('outcome');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            // Why it moved, or why round 1 did not clear — shown to the student.
            $table->text('review_note')->nullable()->after('reviewed_at');

            // What the student originally asked for, kept so a rescheduled slot
            // can show both times ("you asked for X, we moved it to Y").
            $table->timestamp('requested_starts_at')->nullable()->after('review_note');

            $table->index(['interview_round', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table): void {
            $table->dropIndex(['interview_round', 'approval_status']);
            $table->dropColumn([
                'interview_round', 'approval_status', 'outcome',
                'reviewed_by', 'reviewed_at', 'review_note', 'requested_starts_at',
            ]);
        });

        Schema::table('mentor_profiles', function (Blueprint $table): void {
            $table->dropColumn('interview_round');
        });
    }
};
