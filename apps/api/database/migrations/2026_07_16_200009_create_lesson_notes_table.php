<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Class transcript + AI study notes for a `notes` lesson (PRD §6.10) — one per
     * lesson, mirroring the quiz/lab companions. The transcript is manually provided
     * (paste/upload, no ASR); once the notes are approved, the transcript feeds the
     * AI Tutor knowledge base (`knowledge_document_id`).
     */
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recording_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('transcript');
            $table->longText('notes')->nullable();               // AI-generated study notes
            $table->string('source')->default('paste');          // paste|upload
            $table->string('status')->default('draft');          // draft|approved
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
    }
};
