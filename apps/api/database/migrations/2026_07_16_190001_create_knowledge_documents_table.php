<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Tutor knowledge base source documents (PRD §6.4). Built from existing
     * content (course/program descriptions, coding-lab instructions) by
     * `tutor:reindex`, plus trainer-authored `manual` docs. `course_id`/`lesson_id`
     * are citation targets. Chunked into `knowledge_chunks` for lexical retrieval.
     */
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type');                    // course|program|coding_lab|manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('body');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('authored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'source_type', 'source_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
