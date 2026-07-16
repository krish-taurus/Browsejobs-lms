<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI cost log (CLAUDE.md "Every call logs to ai_events"). One row per
     * gateway call: purpose, model, token counts, cost (integer micro-rupees),
     * latency, and status. The per-student daily token budget is enforced by
     * summing today's total_tokens for a user.
     */
    public function up(): void
    {
        Schema::create('ai_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose');
            $table->string('model');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status')->default('ok');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['tenant_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_events');
    }
};
