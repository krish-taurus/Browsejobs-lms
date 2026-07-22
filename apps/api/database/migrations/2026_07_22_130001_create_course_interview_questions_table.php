<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-course, publicly displayed interview-question bank grouped by round — the
 * "questions they were asked" popup on a course page's placement cards. Curated
 * for study; distinct from the internal `real_interview_questions` calibration
 * bank that drives the mock engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_interview_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('company')->nullable();
            $table->unsignedTinyInteger('round_no');
            $table->string('round_name');
            $table->text('question');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'course_id', 'round_no', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_interview_questions');
    }
};
