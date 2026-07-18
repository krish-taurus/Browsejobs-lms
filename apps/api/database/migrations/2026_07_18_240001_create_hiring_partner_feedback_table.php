<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured post-interview feedback from hiring partners (PRD §6.21) — the
 * sharpest gap signal. A placement officer requests feedback for an application;
 * the company submits via a public token link (companies aren't platform users).
 * `gaps` feeds the Advice Graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_partner_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company');
            $table->string('role_title');
            $table->string('token', 64)->unique();
            $table->string('status')->default('requested'); // requested | submitted
            $table->json('ratings')->nullable();     // {technical, problem_solving, communication, culture_fit} 1-5
            $table->unsignedTinyInteger('overall')->nullable(); // 1-5
            $table->boolean('would_hire')->nullable();
            $table->text('strengths')->nullable();
            $table->text('gaps')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_partner_feedback');
    }
};
