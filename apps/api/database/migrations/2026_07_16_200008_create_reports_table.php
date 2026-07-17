<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI-narrated reports + digests (PRD §6.10): weekly student report, trainer
     * pre-class brief, counselor daily digest. The `narrative` is AI or a templated
     * fallback; `data` is the structured P3.2 snapshot the portal renders from.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');                              // weekly_student|trainer_brief|counselor_digest
            $table->string('title');
            $table->text('narrative');
            $table->json('data')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('subject_type')->nullable();          // e.g. App\Models\LiveSession
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('ai_event_id')->nullable()->constrained('ai_events')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipient_id', 'type', 'created_at']);
            // Weekly + digest dedupe by period; trainer briefs (null period) dedupe by subject_id in code.
            $table->unique(['recipient_id', 'type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
