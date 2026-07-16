<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable category → team routing + SLA map (PRD §6.13; the data
     * model's `ticket_teams`). One row per category per tenant. `routing` picks the
     * strategy: `team` (round-robin over a SupportTeam), `batch_trainer` (the
     * student's own batch trainer), or `admin`. SLA targets are per-category minutes.
     */
    public function up(): void
    {
        Schema::create('ticket_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('category');                                          // TicketCategory value
            $table->string('label');
            $table->string('routing');                                           // team|batch_trainer|admin
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedBigInteger('round_robin_pointer')->nullable();       // last assigned user id
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_routes');
    }
};
