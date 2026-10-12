<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shortlist agent (hiring pipeline step 2).
 *
 * The agent ranks candidates for a published JD from up to three sources:
 * BrowseJobs students (the existing talent matcher), the client's own
 * candidate database, and the client's Naukri export. The last two are
 * uploaded by the client as Excel/CSV and live in employer_candidates —
 * owned by one workspace, never visible to another. Naukri has no public
 * API, so a Naukri "source" is always a file the client exported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_candidate_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // client_db | naukri
            $table->string('original_name');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employer_workspace_id', 'source']);
        });

        Schema::create('employer_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_candidate_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20); // client_db | naukri
            // One person per workspace + source: digits of the phone, else the
            // lower-cased email, else a hash of the name. Re-importing the same
            // sheet updates people instead of duplicating them.
            $table->string('dedupe_key', 191);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('current_title')->nullable();
            $table->string('current_company')->nullable();
            $table->string('location')->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('notice_period', 60)->nullable();
            $table->string('current_ctc', 60)->nullable();
            $table->string('expected_ctc', 60)->nullable();
            $table->string('education')->nullable();
            $table->json('skills')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['employer_workspace_id', 'source', 'dedupe_key'], 'employer_candidates_unique_person');
            $table->index(['employer_workspace_id', 'source']);
        });

        Schema::table('employer_jobs', function (Blueprint $table) {
            $table->json('shortlist_settings')->nullable()->after('mock_design');
        });

        Schema::create('job_shortlist_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 20); // publish | manual
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings');
            $table->json('considered'); // per source: how many were looked at / matched
            $table->unsignedInteger('shortlisted_count')->default(0);
            $table->timestamps();

            $table->index(['employer_job_id', 'created_at']);
        });

        Schema::create('job_shortlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_shortlist_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20); // browsejobs | client_db | naukri
            $table->foreignId('candidate_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('employer_candidate_id')->nullable()->constrained()->cascadeOnDelete();
            // "u:12" for a student, "c:45" for a client/Naukri candidate — one row per person per JD.
            $table->string('candidate_key', 40);
            $table->unsignedSmallInteger('match_score');
            $table->unsignedSmallInteger('rank')->default(0);
            $table->json('reasons');
            $table->string('status', 20)->default('shortlisted'); // shortlisted | removed
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['employer_job_id', 'candidate_key']);
            $table->index(['employer_job_id', 'status', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_shortlist_entries');
        Schema::dropIfExists('job_shortlist_runs');
        Schema::table('employer_jobs', function (Blueprint $table) {
            $table->dropColumn('shortlist_settings');
        });
        Schema::dropIfExists('employer_candidates');
        Schema::dropIfExists('employer_candidate_imports');
    }
};
