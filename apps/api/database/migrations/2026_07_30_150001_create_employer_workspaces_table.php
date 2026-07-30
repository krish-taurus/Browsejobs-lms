<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employer module F1 (PRD-E §4 F1, ADR 0051).
 *
 * First real company entity on the platform — every prior "company" is a
 * plain string column (job_postings.company, job_feed_items.company, …).
 * An employer workspace is the demand-side account that owns JDs,
 * pipelines, and credits. Members join via employer_members with a
 * workspace-scoped role (owner | recruiter | hiring_manager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->string('company_size')->nullable(); // e.g. 1-10, 11-50, 51-200, 201-1000, 1000+
            $table->string('gstin')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('locations')->nullable();
            $table->string('status')->default('active'); // active | suspended
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('employer_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // owner | recruiter | hiring_manager (App\Enums\EmployerRole)
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['employer_workspace_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('employer_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role'); // App\Enums\EmployerRole
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['employer_workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_invites');
        Schema::dropIfExists('employer_members');
        Schema::dropIfExists('employer_workspaces');
    }
};
