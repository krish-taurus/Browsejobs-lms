<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignment content for an `assignment`/`project` lesson (PRD §6.5) — one per
     * lesson, mirroring quizzes/coding_labs. `rubric` is the trainer's grading
     * criteria the AI grades against.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->json('rubric')->nullable();                  // [{criterion, max_points}]
            $table->unsignedInteger('max_points')->default(100);
            $table->boolean('allow_link')->default(true);
            $table->boolean('allow_file')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
