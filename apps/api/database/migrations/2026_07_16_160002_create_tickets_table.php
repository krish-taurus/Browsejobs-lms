<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Student support tickets (PRD §6.13). Routed to a team + assignee on create,
     * with per-category first-response/resolution SLA due-times. CSAT + escalation
     * live as columns here (a 1:1-with-ticket concern); the escalation event is also
     * audited + timelined. Status flow: open → in_progress → waiting_on_student →
     * resolved → closed.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference');                                         // human ref e.g. BJ-000123
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('response_warned_at')->nullable();
            $table->timestamp('breached_at')->nullable();                        // first-response breach
            $table->timestamp('resolution_breached_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('csat_rating')->nullable();
            $table->string('csat_comment')->nullable();
            $table->timestamp('csat_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assignee_id', 'status']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
