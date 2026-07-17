<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's attempt at a quiz (PRD §6.5). One per (quiz, student) — the row
     * doubles as the dispatch/reminder tracker (`reminded_at`/`flagged_at`). The
     * server sets `deadline_at` when the student first opens the page (fair timing).
     */
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');        // pending|in_progress|submitted|expired
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('score_pct')->nullable();
            $table->unsignedInteger('correct_count')->nullable();
            $table->unsignedInteger('total_count')->nullable();
            $table->json('answers')->nullable();                 // {question_id: option_index}
            $table->json('integrity')->nullable();               // {tab_blurs, paste_count, duration_sec}
            $table->timestamp('reminded_at')->nullable();        // 48h ladder guard
            $table->timestamp('flagged_at')->nullable();         // 96h ladder guard
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->unique(['quiz_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
