<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The contact timeline (PRD §6.12): every touchpoint on one lead — created,
     * stage change, assignment, note, task, masterclass registration, merge,
     * SLA breach. Rendered chronologically on the lead detail drawer. P2.4
     * messaging + P2.2 payments append their own event types here later.
     */
    public function up(): void
    {
        Schema::create('contact_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // created|stage_changed|assigned|note|task|masterclass_registered|merged|sla_breached
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_timeline_events');
    }
};
