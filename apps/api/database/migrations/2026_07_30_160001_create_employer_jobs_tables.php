<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F2 (PRD-E §4 F2, ADR 0051).
 *
 * employer_jobs is deliberately separate from the staff-curated
 * job_postings board — employer-owned JDs with their own lifecycle
 * (draft → published → paused → closed). Publishing auto-generates a
 * JD-specific mock: jd_mocks holds the versioned question set + grading
 * rubric (immutable per version for score comparability).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('role_family')->nullable();
            $table->json('skills')->nullable();
            $table->unsignedTinyInteger('experience_min_years')->default(0);
            $table->unsignedTinyInteger('experience_max_years')->nullable();
            $table->json('locations')->nullable();
            $table->boolean('remote')->default(false);
            $table->unsignedBigInteger('ctc_min_paise')->nullable();
            $table->unsignedBigInteger('ctc_max_paise')->nullable();
            $table->boolean('ctc_visible')->default(false);
            $table->unsignedSmallInteger('openings')->default(1);
            $table->json('knockout_questions')->nullable();
            $table->string('status')->default('draft'); // draft | published | paused | closed
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'employer_workspace_id', 'status']);
            $table->index(['tenant_id', 'status', 'published_at']);
        });

        Schema::create('jd_mocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status')->default('pending'); // pending | generating | ready | failed
            $table->string('source')->nullable(); // ai | fallback
            $table->json('questions')->nullable();
            $table->json('rubric')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['employer_job_id', 'version']);
            $table->index(['tenant_id', 'employer_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jd_mocks');
        Schema::dropIfExists('employer_jobs');
    }
};
