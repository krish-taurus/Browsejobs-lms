<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-market JD corpus (PRD §6.21). Bulk/manual job descriptions imported by the
 * placement team — NEVER scraped. Each JD is AI-parsed into a skill list; the
 * aggregate skill frequency per role/course is the demand signal behind Curriculum
 * Intelligence. Distinct from `job_postings` (curated placement-board openings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_jds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual'); // manual | csv | partner | api
            $table->string('external_ref')->nullable();
            $table->string('role_title');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->string('seniority')->nullable(); // fresher | junior | mid | senior
            $table->text('raw_jd');
            $table->string('fingerprint', 64);
            $table->json('extracted_skills')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('parse_status')->default('pending'); // pending | parsed | failed
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('ingested_at')->useCurrent();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('course_id');
            $table->index(['tenant_id', 'role_title']);
            $table->unique(['tenant_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_jds');
    }
};
