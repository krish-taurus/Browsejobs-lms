<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingested job-feed postings (PRD §6.22). Normalised from any source, deduped on
 * a fingerprint, skill-extracted through the §6.21 JD pipeline, freshness-tracked
 * (stale items auto-expire), and quality-filtered. The "Jobs for You" feed scores
 * these per student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_feed_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->string('work_mode')->nullable();
            $table->text('description');
            $table->string('apply_url')->nullable();
            $table->string('source_kind');
            $table->string('role_title')->nullable();
            $table->json('extracted_skills')->nullable();
            $table->string('seniority')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('fingerprint', 64);
            $table->string('status')->default('active'); // active | expired
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ingested_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'fingerprint']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_feed_items');
    }
};
