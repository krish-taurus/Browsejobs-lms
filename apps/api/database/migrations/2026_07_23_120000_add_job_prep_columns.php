<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-prep layer (ADR 0048):
 * - `job_feed_items.prep_questions` caches the AI-generated "potential interview
 *   questions" for a posting — generated once per job, served to everyone.
 * - `mock_blueprints.job_feed_item_id` marks a blueprint spun from a specific
 *   posting's JD (the "quick mock for this job" button); such blueprints are
 *   hidden (is_active=false) so course pickers never list them, and course_id
 *   becomes nullable because an open-registration job-seeker has no course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_feed_items', function (Blueprint $table): void {
            $table->json('prep_questions')->nullable()->after('extracted_skills');
        });

        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->foreignId('job_feed_item_id')->nullable()->after('course_id')
                ->constrained('job_feed_items')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_feed_item_id');
        });

        Schema::table('job_feed_items', function (Blueprint $table): void {
            $table->dropColumn('prep_questions');
        });
    }
};
