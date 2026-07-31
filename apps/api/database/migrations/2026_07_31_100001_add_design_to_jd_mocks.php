<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employer's mock design (PRD-E F16): which skills the interview should
 * focus on, how much each competency is worth, and the mix of question
 * formats.
 *
 * Nullable and additive — a JD with no design keeps generating the way it
 * always has, from the description alone. The design is stored on the job
 * rather than the mock so it survives regeneration: an employer who tuned
 * the weighting should not lose it when they regenerate the questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_jobs', function (Blueprint $table): void {
            $table->json('mock_design')->nullable()->after('knockout_questions');
        });
    }

    public function down(): void
    {
        Schema::table('employer_jobs', function (Blueprint $table): void {
            $table->dropColumn('mock_design');
        });
    }
};
