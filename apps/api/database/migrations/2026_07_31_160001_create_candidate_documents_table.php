<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents a candidate uploads for verification, assessed one at a time
 * (PRD-E F9).
 *
 * The `documents` check used to carry a single free-text path, so a candidate
 * with an offer letter, two payslips and a relieving letter had one row and a
 * human read all four with no record of which one was the problem. Each file
 * is now its own row with its own assessment.
 *
 * `extract_status` is the honest part. We can read .docx and plain text; we
 * cannot read a PDF or a photo — there is no extractor or OCR in the stack.
 * Recording that a file was unreadable, and routing it to a human, is the
 * alternative to an AI "assessment" of a filename.
 *
 * `ai_verdict` is advice for the reviewer, never a decision. A verified badge
 * is a claim we make to employers about a person; a human settles it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 40);          // offer_letter | payslip | relieving_letter | ...
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);

            $table->string('extract_status', 20)->default('pending'); // pending|extracted|unreadable
            $table->string('review_status', 20)->default('pending');  // pending|accepted|rejected

            $table->json('ai_verdict')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id', 'cd_tenant_idx');
            $table->index(['user_id', 'review_status'], 'cd_user_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
    }
};
