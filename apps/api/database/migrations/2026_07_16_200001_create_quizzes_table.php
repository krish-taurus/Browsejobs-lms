<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quiz content for a `quiz` lesson (PRD §6.5) — one row per lesson, mirroring
     * coding_labs. AI-generated or manual; only an `approved` quiz dispatches.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('time_limit_sec')->default(600);
            $table->unsignedTinyInteger('pass_pct')->default(60);
            $table->boolean('shuffle')->default(true);
            $table->string('status')->default('draft');          // draft|approved
            $table->string('source')->default('manual');         // ai|manual
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->unique('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
