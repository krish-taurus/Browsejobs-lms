<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotes captured leads into worked CRM records (PRD §6.12): pipeline
     * stage, counselor assignment, rule-based score, normalized phone for
     * dedupe, speed-to-lead SLA timestamps, and a merge tombstone.
     *
     * NOTE: SQLite (used by the test suite) cannot add foreign-key constraints
     * via ALTER TABLE, so these columns carry indexes only; referential
     * integrity is enforced in the Action layer. The fresh CRM tables
     * (lead_stages, crm_tasks, ...) keep real FKs in their create migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('lead_stage_id')->nullable()->after('crm_synced');
            $table->foreignId('assigned_to')->nullable()->after('lead_stage_id');
            $table->unsignedSmallInteger('score')->default(0)->after('assigned_to');
            $table->string('phone_normalized')->nullable()->after('phone');
            $table->timestamp('first_touch_at')->nullable()->after('score');
            $table->timestamp('sla_due_at')->nullable()->after('first_touch_at');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_due_at');
            $table->foreignId('merged_into_id')->nullable()->after('sla_breached_at');

            $table->index('lead_stage_id');
            $table->index('assigned_to');
            $table->index('sla_due_at');
            $table->index('merged_into_id');
            $table->index(['tenant_id', 'phone_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['lead_stage_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['sla_due_at']);
            $table->dropIndex(['merged_into_id']);
            $table->dropIndex(['tenant_id', 'phone_normalized']);
            $table->dropColumn([
                'lead_stage_id', 'assigned_to', 'score', 'phone_normalized',
                'first_touch_at', 'sla_due_at', 'sla_breached_at', 'merged_into_id',
            ]);
        });
    }
};
