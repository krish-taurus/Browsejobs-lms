<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F18 — the interview process an employer designs per JD.
 *
 * Until now every JD ran a fixed L1 then L2, and both rounds asked the same
 * questions because both snapshotted the same JD mock. This table is the
 * definition of a round: what it tests, how long the candidate has, and
 * whether it goes out by hand or on a score threshold.
 *
 * `key` is what lands in employer_interviews.round, so the existing unique
 * (application, round) constraint keeps doing the work of "one interview per
 * round per candidate". The two rounds that exist today keep the keys `l1`
 * and `l2`, which is why no backfill of the interviews table is needed.
 *
 * Index and FK names are given explicitly and kept short: Laravel's
 * generated names for this table's columns exceed MySQL's 64-character
 * identifier limit, and SQLite does not enforce it, so the failure would
 * only appear in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_job_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(0);
            $table->string('key', 32);
            $table->string('name');
            $table->string('kind')->default('ai_interview'); // ai_interview | mcq | human

            // Same vocabulary as the mock designer, so a skill named on a
            // round is the same string the JD and the rubric compare.
            $table->json('focus_skills')->nullable();
            $table->json('competency_weights')->nullable();
            $table->json('format_mix')->nullable();
            $table->unsignedTinyInteger('question_count')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedSmallInteger('window_hours')->default(48);

            // manual: an employer sends it. auto: it goes out on its own the
            // moment the previous score clears auto_min_score.
            $table->string('dispatch')->default('manual');
            $table->unsignedTinyInteger('auto_min_score')->nullable();

            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('tenant_id', 'ejr_tenant_idx');
            $table->unique(['employer_job_id', 'key'], 'ejr_job_key_uq');
            $table->index(['employer_job_id', 'position'], 'ejr_job_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_job_rounds');
    }
};
