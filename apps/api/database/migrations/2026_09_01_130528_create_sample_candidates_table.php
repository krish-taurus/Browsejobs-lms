<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entirely separate from real candidates (users/cv_profiles/student_scores)
 * and never touched by real matching, scoring, or messaging code — this
 * table exists ONLY to power an opt-in "preview with sample data" toggle on
 * the employer Talent Pool, for demoing the product before real candidate
 * volume exists. No code path here ever contacts a phone/email in this
 * table: the numbers and addresses are synthetic and not meant to be
 * dialled or messaged (Sept 2026, PRD: sample-data preview mode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('location');
            $table->string('education');
            $table->string('experience_summary');
            $table->unsignedTinyInteger('experience_years');
            $table->json('skills');
            $table->text('cv_summary');
            $table->unsignedTinyInteger('ai_readiness_score');
            $table->unsignedTinyInteger('cv_mock_score');
            $table->string('lead_status')->default('New');
            $table->boolean('bgb_verified')->default(true);
            $table->timestamps();

            $table->index('ai_readiness_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_candidates');
    }
};
