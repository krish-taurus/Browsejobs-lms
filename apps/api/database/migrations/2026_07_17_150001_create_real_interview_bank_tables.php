<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.2 — Real Interview Intelligence (PRD §6.6). Transcript ingestion pipeline
 * (source-agnostic uploads → AI parse → anonymise → review queue) feeding the
 * real_interview_questions bank that mocks draw from and gap reports compare
 * against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_transcripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('uploader_id')->index();
            $table->foreignId('course_id')->nullable()->index();
            // parakeet | text | file | audio | zip | debrief
            $table->string('source', 20);
            $table->string('original_name')->nullable();
            // Encrypted original blob on the s3 disk (legally sensitive).
            $table->string('encrypted_path')->nullable();
            // Anonymised working text — the ONLY copy the parser ever sees.
            $table->longText('raw_text')->nullable();
            $table->string('role_title')->nullable();
            $table->string('outcome', 40)->nullable();
            // pending | transcribing | parsing | parsed | failed
            $table->string('status', 20)->default('pending')->index();
            $table->string('parse_error', 500)->nullable();
            $table->unsignedInteger('questions_found')->default(0);
            // Uploader confirmed the right to share (consent checkbox, logged).
            $table->timestamp('consent_confirmed_at');
            $table->timestamps();
        });

        Schema::create('real_interview_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('interview_transcript_id')->nullable()->index();
            $table->foreignId('course_id')->nullable()->index();
            $table->string('role_title');
            $table->text('question');
            $table->text('normalized_question');
            // sha1(role|normalized) — dedupe key; repeats bump asked_count.
            $table->string('fingerprint', 64);
            $table->json('topic_tags');
            $table->string('difficulty', 20)->nullable();
            $table->string('round_type', 40)->nullable();
            $table->json('follow_ups')->nullable();
            $table->text('strong_answer')->nullable();
            $table->text('struggle_points')->nullable();
            $table->string('outcome', 40)->nullable();
            // Parser self-assessment — drives batch-approve of high-confidence rows.
            $table->string('confidence', 10)->default('medium');
            // pending | approved | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('asked_count')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_interview_questions');
        Schema::dropIfExists('interview_transcripts');
    }
};
