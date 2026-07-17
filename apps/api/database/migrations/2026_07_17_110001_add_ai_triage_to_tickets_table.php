<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI triage signals (PRD §6.13, P3.5d-b): category suggestion, urgency, sentiment,
 * duplicate detection.
 *
 * These live in their own `ai_*` columns rather than overwriting `category`,
 * `priority`, or `support_team_id`. Category and routing stay human/rule-owned — the
 * PRD asks for a category *suggestion*, and silently re-routing a ticket on a model's
 * say-so would move it between teams with nobody accountable.
 *
 * `priority` is the one exception and is treated carefully: triage may *raise* it (the
 * PRD's "an angry payment dispute jumps the queue"), never lower it, only on the first
 * triage of an untouched ticket, and only with `ai_priority_raised` set so staff can
 * see it was not a human and undo it. Priority does not affect SLA due-times (those are
 * per-category, from `ticket_routes`) — a jump changes who is seen first, not what was
 * promised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ai_category')->nullable()->after('category');          // suggestion only
            $table->string('ai_urgency')->nullable()->after('priority');           // TicketPriority value
            $table->string('ai_sentiment')->nullable()->after('ai_urgency');       // TicketSentiment value
            $table->boolean('ai_priority_raised')->default(false)->after('ai_sentiment');
            $table->foreignId('ai_duplicate_of_id')->nullable()->after('ai_priority_raised')
                ->constrained('tickets')->nullOnDelete();
            $table->timestamp('ai_triaged_at')->nullable()->after('ai_duplicate_of_id');
            $table->foreignId('ai_event_id')->nullable()->after('ai_triaged_at')
                ->constrained('ai_events')->nullOnDelete();

            // The staff queue sorts by priority, then recency.
            $table->index(['tenant_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'priority']);
            $table->dropConstrainedForeignId('ai_duplicate_of_id');
            $table->dropConstrainedForeignId('ai_event_id');
            $table->dropColumn(['ai_category', 'ai_urgency', 'ai_sentiment', 'ai_priority_raised', 'ai_triaged_at']);
        });
    }
};
