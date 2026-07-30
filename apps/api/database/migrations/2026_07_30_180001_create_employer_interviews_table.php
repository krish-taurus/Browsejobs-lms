<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F5 (PRD-E §4 F5, ADR 0051).
 *
 * One row per interview round (L1/L2) per application. question_set is
 * a snapshot taken at invite time (from the JD mock version or the
 * employer's own bank) so later regenerations never change what a
 * candidate was actually asked. Grading is queued; on AI failure the
 * round honestly stays `submitted` ("grading delayed") — no fabricated
 * scores, per the trust firewall.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_application_id')->constrained()->cascadeOnDelete();
            $table->string('round'); // l1 | l2 (App\Enums\EmployerInterviewRound)
            $table->string('status')->default('invited'); // invited | in_progress | submitted | graded | expired
            $table->json('question_set');
            $table->json('rubric');
            $table->json('answers')->nullable();
            $table->json('dimension_scores')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->text('grading_summary')->nullable();
            $table->string('grading_source')->nullable(); // ai
            $table->timestamp('invited_at');
            $table->timestamp('expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['employer_job_application_id', 'round']);
            $table->index(['tenant_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_interviews');
    }
};
