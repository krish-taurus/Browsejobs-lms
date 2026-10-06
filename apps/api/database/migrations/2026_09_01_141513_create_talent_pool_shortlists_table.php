<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Talent Pool candidate an employer has shortlisted straight from the
 * pool, before any formal application exists — lighter than
 * EmployerJobApplication on purpose: a Talent Pool candidate hasn't been
 * through this JD's own mock interview yet, so the real application
 * pipeline's prerequisites (cv_document_id, jd_mock_id, …) don't hold for
 * them. This just records the action and gates the WhatsApp notification
 * to once per (job, candidate) — it is not a pipeline stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_pool_shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shortlisted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employer_job_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_pool_shortlists');
    }
};
