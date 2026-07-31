<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F3 (PRD-E §4 F3/F5, ADR 0051).
 *
 * Applications are free (compliance: candidates never pay for access —
 * paid mock packs buy preparation + ranked advantage). Graded
 * applications carry a denormalized mock_score for ranking plus a
 * read-only link to the MockInterview evidence. Every stage change is
 * recorded in application_stage_transitions with actor attribution
 * (user or automation rule) — the candidate timeline the PRD requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        // This migration failed part-way on MySQL the first time it ran, before
        // it was ever recorded as complete: one generated index name exceeded
        // MySQL's 64-character identifier limit (SQLite does not enforce that,
        // so the suite passed). MySQL DDL is not transactional, so it left an
        // orphaned table behind that Laravel does not know about.
        //
        // Repair that state, but only when the table is provably empty — if it
        // somehow holds rows, fall through and let the create fail loudly
        // rather than destroy data.
        if (Schema::hasTable('employer_job_applications')
            && DB::table('employer_job_applications')->doesntExist()) {
            Schema::dropIfExists('application_stage_transitions'); // FK dependent
            Schema::dropIfExists('employer_job_applications');
        }

        Schema::create('employer_job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('jd_mock_id')->nullable()->constrained('jd_mocks')->nullOnDelete();
            $table->foreignId('mock_interview_id')->nullable()->constrained('mock_interviews')->nullOnDelete();
            $table->unsignedTinyInteger('mock_score')->nullable(); // 0-100, denormalized for ranking
            $table->timestamp('graded_at')->nullable();
            $table->string('stage')->default('applied'); // App\Enums\EmployerApplicationStage
            $table->json('knockout_answers')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['employer_job_id', 'candidate_id']);
            $table->index(['tenant_id', 'employer_job_id', 'stage'], 'eja_tenant_job_stage_idx');
            $table->index(['tenant_id', 'employer_job_id', 'mock_score'], 'eja_tenant_job_score_idx');
            $table->index(['tenant_id', 'candidate_id'], 'eja_tenant_candidate_idx');
        });

        Schema::create('application_stage_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Named explicitly: the generated constraint name would be 65
            // characters, one over MySQL's identifier limit.
            $table->unsignedBigInteger('employer_job_application_id');
            $table->foreign('employer_job_application_id', 'ast_application_fk')
                ->references('id')->on('employer_job_applications')->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->string('actor_type'); // user | rule | system
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'employer_job_application_id'], 'ast_tenant_application_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stage_transitions');
        Schema::dropIfExists('employer_job_applications');
    }
};
