<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pluggable job-feed sources (PRD §6.22). Each row configures one source behind
 * the `JobFeedSource` adapter interface — internal postings, hiring-partner feeds,
 * a licensed job API, public ATS endpoints, or CSV/manual. NO raw portal scraping.
 * Higher `priority` surfaces first (partner feeds are priority-flagged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_feed_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind'); // internal | partner | api | ats | csv
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_feed_sources');
    }
};
