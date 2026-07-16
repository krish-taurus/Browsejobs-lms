<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant freemium config (PRD §6.17): included quotas per enrolment type,
     * the self-paced % of live price, and feature toggles. One row per tenant
     * (firstOrCreate), like the CRM assignment rule.
     */
    public function up(): void
    {
        Schema::create('monetization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cv_free_grants')->default(3);
            $table->unsignedInteger('voice_included_live')->default(5);
            $table->unsignedInteger('voice_included_self_paced')->default(2);
            $table->unsignedInteger('self_paced_pct_bps')->default(5000);        // 50%
            $table->boolean('text_practice_enabled')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monetization_settings');
    }
};
