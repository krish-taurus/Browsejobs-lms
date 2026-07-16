<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coding-lab content for a `coding_lab` lesson (PRD §6.5): language, starter
     * code, instructions, and hidden test cases. One row per lesson.
     */
    public function up(): void
    {
        Schema::create('coding_labs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('language')->default('python');
            $table->text('instructions')->nullable();
            $table->text('starter_code')->nullable();
            $table->json('test_cases')->nullable();          // [{stdin, expected_stdout}]
            $table->unsignedInteger('time_limit_ms')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coding_labs');
    }
};
