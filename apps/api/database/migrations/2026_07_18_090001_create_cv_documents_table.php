<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.5a — AI CV generator (PRD §6.7). Versioned, structured CV documents:
 * auto-built free on course completion, regenerated/JD-tailored on credits,
 * placement-officer approved before placement use, shareable by token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('course_id')->nullable()->index();
            $table->unsignedInteger('version');
            $table->string('title');
            // auto | manual | jd_tailored — auto is the credit-free first build.
            $table->string('source', 20)->default('manual');
            // ai | fallback — whether the LLM or the deterministic assembler wrote it.
            $table->string('content_source', 10)->default('ai');
            $table->json('content');
            $table->text('jd_excerpt')->nullable();
            $table->json('ats')->nullable();
            // draft | approved — approval gates placement use (PRD §6.7).
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_documents');
    }
};
