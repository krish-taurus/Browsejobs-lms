<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI Readiness Interview (candidate request, Aug 2026): a general,
 * CV-driven mock — not tied to any single job, unlike the JD-specific
 * blueprints this table already carries. `user_id` marks it: the one new
 * blueprint kind that belongs to a single student rather than being shared
 * across everyone who takes it (mirrors the employer_job_id/job_feed_item_id
 * "kind inferred from which FK is set" convention already in this table).
 * `max_questions` lets this kind run a longer session (15) than the
 * platform's normal default (6, config('mocks.max_questions')) without
 * changing that default for every other mock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('employer_job_id')
                ->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('max_questions')->nullable()->after('opening_question');
        });

        Schema::table('cv_profiles', function (Blueprint $table): void {
            // Gate for Talent Pool visibility (LmsTalentMatcher): completing
            // this interview is what makes a student's profile discoverable
            // by an employer who never applied to any of their jobs — today
            // that gate doesn't exist at all (any CV with an overlapping
            // skill is already shown), so this tightens, not just adds.
            $table->unsignedTinyInteger('cv_mock_score')->nullable()->after('data');
            $table->timestamp('cv_mock_completed_at')->nullable()->after('cv_mock_score');
        });
    }

    public function down(): void
    {
        Schema::table('cv_profiles', function (Blueprint $table): void {
            $table->dropColumn(['cv_mock_score', 'cv_mock_completed_at']);
        });

        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('max_questions');
        });
    }
};
