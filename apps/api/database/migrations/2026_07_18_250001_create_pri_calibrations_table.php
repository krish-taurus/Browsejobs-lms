<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRI weight calibration from placement outcomes (PRD §6.21). Each row is one
 * tenant's derived mastery/engagement/attendance weights, computed by correlating
 * those signals with who actually got placed. The ScoreCalculator reads the
 * latest applied row and falls back to config when none exists (small cohort).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pri_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('weights');       // {mastery, engagement, attendance}, sums to 1
            $table->json('correlations');  // signal => point-biserial r, for transparency
            $table->unsignedInteger('cohort_size');
            $table->boolean('applied')->default(false);
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'applied', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pri_calibrations');
    }
};
