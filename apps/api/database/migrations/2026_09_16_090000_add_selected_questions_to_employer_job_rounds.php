<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F18 — lets an employer hand-pick the exact questions an AI
 * interview round asks, instead of only steering the pick via focus_skills.
 *
 * Stored as the questions' own `text` (matching how SendInterviewRound
 * already identifies a question everywhere else — see its "already asked"
 * dedup), not a numeric id: JdMock question ids are assigned fresh on every
 * regeneration and are not guaranteed unique or stable across versions, so an
 * id saved today could point at different content tomorrow. Text is exactly
 * as unstable across a regenerate, but that is visible and expected — a
 * regenerated bank is a new bank, and the round falls back to its focus-skill
 * behaviour when none of its picks resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_job_rounds', function (Blueprint $table): void {
            $table->json('selected_questions')->nullable()->after('format_mix');
        });
    }

    public function down(): void
    {
        Schema::table('employer_job_rounds', function (Blueprint $table): void {
            $table->dropColumn('selected_questions');
        });
    }
};
