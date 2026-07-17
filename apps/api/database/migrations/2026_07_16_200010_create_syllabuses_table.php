<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI-generated, trainer-approved course syllabus (PRD §6.2). One row per course.
     * The AI drafts `content` from the curriculum tree; a trainer approves; the row
     * renders to a branded document (`storage_path`). Module-level syllabi are a
     * later extension (a nullable module_id row) — this slice is course-level only.
     */
    public function up(): void
    {
        Schema::create('syllabuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();          // AI markdown-light prose; null until generated/authored
            $table->string('status')->default('draft');       // SyllabusStatus draft|approved
            $table->string('curriculum_hash', 64)->nullable(); // hash of the course tree at generation → staleness signal
            $table->boolean('is_stale')->default(false);      // curriculum changed after the current approved version
            $table->string('render_status')->default('pending'); // pending|rendered
            $table->string('storage_path')->nullable();       // rendered branded doc on the render disk
            $table->unsignedInteger('version')->default(1);   // increments when a new draft supersedes an approved version
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('course_id'); // one syllabus per course
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabuses');
    }
};
