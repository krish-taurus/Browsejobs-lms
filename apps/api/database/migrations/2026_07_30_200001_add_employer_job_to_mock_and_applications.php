<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the existing mock engine to employer JDs (PRD-E F3).
 *
 * `mock_blueprints.employer_job_id` mirrors the `job_feed_item_id` pattern
 * from 2026_07_23_120000: a blueprint spun from one employer JD, hidden from
 * course pickers, so the answer/finish/scorecard engine runs unchanged. A
 * candidate applying to an internal posting sits a real mock rather than a
 * separate parallel implementation.
 *
 * On the application side, `mock_interview_id` records which attempt produced
 * the standing score (candidates may retake while their package holds — best
 * attempt wins), and `cv_document_id` holds the JD-tailored ATS CV built at
 * apply time, the same courtesy external applications already get.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->foreignId('employer_job_id')->nullable()->after('job_feed_item_id')
                ->constrained('employer_jobs')->nullOnDelete();
        });

        Schema::table('employer_job_applications', function (Blueprint $table): void {
            // mock_interview_id already exists from the F3 migration — this
            // adds only the JD-tailored CV and the retake counter.
            $table->foreignId('cv_document_id')->nullable()->after('mock_interview_id')
                ->constrained('cv_documents')->nullOnDelete();
            $table->unsignedSmallInteger('mock_attempts')->default(0)->after('cv_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('employer_job_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cv_document_id');
            $table->dropColumn('mock_attempts');
        });

        Schema::table('mock_blueprints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employer_job_id');
        });
    }
};
