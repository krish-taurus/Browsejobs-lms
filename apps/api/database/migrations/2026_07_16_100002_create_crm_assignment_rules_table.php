<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant counselor assignment configuration (PRD §6.12 "assignment rules
     * round-robin / by course") plus the speed-to-lead SLA window. One row per
     * tenant; `round_robin_pointer` stores the last-assigned counselor so the
     * next capture advances the wheel deterministically.
     */
    public function up(): void
    {
        Schema::create('crm_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode')->default('round_robin'); // round_robin|by_course
            $table->json('by_course_map')->nullable();       // { course_slug: user_id }
            $table->foreignId('round_robin_pointer')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sla_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_assignment_rules');
    }
};
