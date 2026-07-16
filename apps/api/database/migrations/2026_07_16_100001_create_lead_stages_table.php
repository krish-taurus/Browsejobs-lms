<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The CRM pipeline funnel (PRD §6.12). Per-tenant, ordered stages the lead
     * kanban renders as columns: Lead -> Masterclass Registered -> Attended ->
     * Bootcamp -> Reserved/Payment Pending -> Enrolled -> Active ->
     * Placement-Ready -> Placed. Seeded per tenant by CrmSeeder.
     */
    public function up(): void
    {
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'position']);
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_stages');
    }
};
