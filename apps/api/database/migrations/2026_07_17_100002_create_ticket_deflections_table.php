<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every AI first-response offered before a ticket is raised (PRD §6.13, P3.5d-a).
 *
 * This table exists to make the PRD's "typically deflects 30–50% of volume" claim
 * *measurable* rather than asserted: `outcome` records what the student actually did
 * with the answer. Deflection quality is a function of the support corpus, and this
 * is the only honest way to find out whether that corpus is good enough — a low
 * accept rate means the policy documents are thin, not that the model is bad.
 *
 * A row is written when an answer is offered. `ticket_id` backfills when the student
 * proceeds anyway, so an offered→proceeded pair is traceable to the ticket it failed
 * to prevent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_deflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');
            $table->text('question');
            $table->text('answer');
            $table->string('confidence');                       // high|medium|low
            $table->json('cited_chunk_ids')->nullable();
            $table->string('outcome')->default('offered');      // offered|accepted|proceeded
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_event_id')->nullable()->constrained('ai_events')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'outcome']);
            $table->index(['tenant_id', 'category', 'outcome']);
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_deflections');
    }
};
