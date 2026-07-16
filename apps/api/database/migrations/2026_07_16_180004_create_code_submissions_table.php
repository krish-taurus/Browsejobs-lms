<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's code run/submission against a lab (PRD §6.4/§6.5) — the
     * code-telemetry source. `kind` = run|submit; `passed_tests`/`total_tests`
     * apply to submissions.
     */
    public function up(): void
    {
        Schema::create('code_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language');
            $table->longText('source');
            $table->text('stdin')->nullable();
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->string('status')->nullable();            // Judge0 status text
            $table->unsignedInteger('passed_tests')->default(0);
            $table->unsignedInteger('total_tests')->default(0);
            $table->unsignedInteger('run_time_ms')->default(0);
            $table->unsignedInteger('memory_kb')->default(0);
            $table->string('kind')->default('run');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_submissions');
    }
};
