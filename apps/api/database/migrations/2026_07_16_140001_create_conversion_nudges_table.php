<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bootcamp-conversion nudge log (PRD §5 Stage 3). One row per fee plan per
     * ladder rung; the unique (fee_plan_id, rung) key makes the daily
     * `conversions:run-nudges` command idempotent — each nudge sends once.
     */
    public function up(): void
    {
        Schema::create('conversion_nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->string('rung');       // day-1|day-3|day-7|task
            $table->string('channel')->default('log');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['fee_plan_id', 'rung']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_nudges');
    }
};
