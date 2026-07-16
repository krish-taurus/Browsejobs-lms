<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fee-driven access blocks (PRD §6.8 / §8 `access_blocks`). A `soft` block
     * locks live classes + recordings; a `hard` block escalates to fee-screen
     * only + a counselor task. `lifted_at` null == active; payment lifts it
     * instantly. The FeeGate reads active rows to allow/deny access.
     */
    public function up(): void
    {
        Schema::create('access_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // student
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level');                 // soft|hard
            $table->string('reason')->nullable();
            $table->timestamp('blocked_at');
            $table->timestamp('lifted_at')->nullable();
            $table->string('lifted_reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_blocks');
    }
};
