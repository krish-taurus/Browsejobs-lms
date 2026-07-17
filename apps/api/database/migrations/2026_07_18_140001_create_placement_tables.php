<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.5b — placement pipeline (PRD §6.11): staff-curated job board,
 * gated applications with a stage pipeline, per-round debriefs (which feed
 * the P4.2 interview bank), and offer/placement tracking for the Proof
 * Engine and the pay-after-placement milestone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('course_id')->nullable()->index();
            $table->foreignId('posted_by')->nullable();
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->string('work_mode', 20)->nullable(); // onsite | hybrid | remote
            $table->string('salary_range')->nullable();
            $table->text('description');
            $table->string('apply_url', 500)->nullable();
            // open | closed
            $table->string('status', 20)->default('open')->index();
            $table->date('closes_on')->nullable();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('job_posting_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('cv_document_id')->nullable();
            // applied | shortlisted | interviewing | offer | placed | rejected | withdrawn
            $table->string('status', 20)->default('applied')->index();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('offer_ctc_paise')->nullable();
            $table->string('offer_role')->nullable();
            $table->timestamp('offer_accepted_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            // Consent-first celebrations (PRD §6.18): captured at apply time.
            $table->timestamp('celebrate_consent_at')->nullable();
            $table->boolean('celebrate_anonymous')->default(false);
            $table->timestamps();

            $table->unique(['job_posting_id', 'user_id']);
        });

        // One row per real interview round; the debrief text flows into the
        // P4.2 transcript pipeline so every round enriches the bank.
        Schema::create('job_application_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('job_application_id')->index();
            $table->unsignedTinyInteger('round_no');
            $table->string('round_type', 40)->nullable();
            $table->date('happened_on');
            // advanced | rejected | pending | offer
            $table->string('outcome', 20)->default('pending');
            $table->text('debrief')->nullable();
            $table->foreignId('interview_transcript_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_rounds');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
    }
};
