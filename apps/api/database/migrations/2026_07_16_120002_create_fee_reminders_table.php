<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dunning reminder log (PRD §6.8). One row per instalment per ladder rung;
     * the unique (instalment_id, rung) key makes the daily `fees:run-ladder`
     * command idempotent — each rung sends exactly once.
     */
    public function up(): void
    {
        Schema::create('fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instalment_id')->constrained()->cascadeOnDelete();
            $table->string('rung');       // t-7|t-3|t-1|due|grace|soft_block|hard_block
            $table->string('channel')->default('log');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['instalment_id', 'rung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_reminders');
    }
};
