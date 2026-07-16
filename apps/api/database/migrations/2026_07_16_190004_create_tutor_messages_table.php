<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One turn in an AI Tutor conversation (PRD §6.4). Student turns carry a
     * `question_fingerprint` (normalized token hash) for repeat-question detection;
     * tutor turns carry cited chunk ids, a confidence signal, and — when escalated —
     * the `ticket_id` of the academic ticket raised to the trainer.
     */
    public function up(): void
    {
        Schema::create('tutor_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('tutor_conversations')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');                              // student|tutor
            $table->text('body');
            $table->json('cited_chunk_ids')->nullable();
            $table->string('confidence')->nullable();            // high|medium|low
            $table->boolean('escalated')->default(false);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_fingerprint')->nullable();
            $table->foreignId('ai_event_id')->nullable()->constrained('ai_events')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'student_id', 'question_fingerprint', 'created_at'], 'tutor_messages_repeat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_messages');
    }
};
