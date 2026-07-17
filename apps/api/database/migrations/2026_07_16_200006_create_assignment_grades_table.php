<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI-drafted, trainer-released grade for a submission (PRD §6.5). Hidden from
     * the student until `status` is approved. `ai_likelihood` is a trainer-only
     * advisory signal — never serialized to a student.
     */
    public function up(): void
    {
        Schema::create('assignment_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->string('status')->default('draft');          // draft|approved
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_points')->default(100);
            $table->text('feedback')->nullable();
            $table->json('criteria')->nullable();                // [{criterion, score, note}]
            $table->unsignedTinyInteger('ai_likelihood')->nullable(); // 0-100, TRAINER-ONLY
            $table->foreignId('ai_event_id')->nullable()->constrained('ai_events')->nullOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->unique('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_grades');
    }
};
