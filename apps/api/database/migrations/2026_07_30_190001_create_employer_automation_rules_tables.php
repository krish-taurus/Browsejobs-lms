<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F6 (PRD-E §4 F6, ADR 0051).
 *
 * Per-JD automation rules stored as data and evaluated in queued
 * listeners on domain events — never inline in requests. Hard
 * guardrails (enforced at validation AND evaluation): automation can
 * advance to shortlisted/l1/l2 or park for review; it can never
 * terminally reject and never release offers. Every firing writes an
 * employer_automation_runs row — the audit trail behind "why did this
 * candidate move".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('trigger'); // application_graded | interview_graded
            $table->string('round')->nullable(); // l1 | l2 (interview_graded only)
            $table->unsignedTinyInteger('min_score'); // 0-100 threshold
            $table->string('action'); // advance | park
            $table->string('target_stage')->nullable(); // shortlisted | l1 | l2 (advance only)
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'employer_job_id', 'enabled'], 'ear_tenant_job_enabled_idx');
        });

        Schema::create('employer_automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_application_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('outcome'); // applied | skipped_below_threshold | skipped_invalid_transition
            $table->unsignedTinyInteger('score_seen')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'employer_automation_rule_id'], 'earun_tenant_rule_idx');
            $table->index(['tenant_id', 'employer_job_application_id'], 'earun_tenant_application_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_automation_runs');
        Schema::dropIfExists('employer_automation_rules');
    }
};
