<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The latest rule-based scores for a student (PRD §6.4) — stands in for the
     * §8 `students(risk, pri, engagement)` attributes. One row per student. The
     * mastery map + Next Best Action are derived and stored as json. The counselor
     * risk dashboard reads this; the Coach Panel refreshes it live.
     */
    public function up(): void
    {
        Schema::create('student_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('engagement')->default(0);
            $table->unsignedTinyInteger('risk_dropout')->default(0);
            $table->unsignedTinyInteger('risk_placement')->default(0);
            $table->unsignedTinyInteger('pri')->default(0);
            $table->unsignedInteger('streak_days')->default(0);
            $table->json('mastery')->nullable();       // [{module_id, name, pct, band}]
            $table->json('next_action')->nullable();   // {key, title, detail, href}
            $table->json('red_flags')->nullable();     // ["fee_overdue", "low_engagement", ...]
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'risk_dropout']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_scores');
    }
};
