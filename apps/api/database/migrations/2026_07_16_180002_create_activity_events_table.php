<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The student telemetry stream (PRD §6.4): logins, watch time, lesson/topic/
     * module completion, and code metrics. Downstream P3.2 scores (mastery,
     * engagement, risk, PRI) read this. `value` is a numeric payload (seconds,
     * score, count); `subject_*` optionally references the thing acted on.
     */
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->integer('value')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'type']);
            $table->index(['tenant_id', 'user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
