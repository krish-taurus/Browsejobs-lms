<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Syllabus Recommendation Reports (PRD §6.21). One report per course generation
 * (quarterly or on-demand): AI-proposed add/expand/deprioritise items, each with
 * its evidence (interview-bank frequency, JD demand share, debrief failure
 * points). Trainer/admin approves or rejects; an approved report is the decision
 * record behind a curriculum change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('source')->default('on_demand'); // on_demand | scheduled
            $table->string('content_source')->default('ai'); // ai | fallback
            $table->text('summary')->nullable();
            $table->json('items');     // list of {action, topic, rationale, evidence}
            $table->json('evidence');  // the aggregates used, for transparency
            $table->unsignedSmallInteger('market_sample')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_recommendations');
    }
};
