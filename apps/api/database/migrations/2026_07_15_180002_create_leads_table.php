<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lead capture (Platform Spec §6.1 + PRD Phase 1 "lead capture foundation").
     * Every marketing form lands here with UTM attribution and logged consent
     * (DPDP). CRM sync fields let the P2.1 CRM pick these up unchanged.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('lead_type'); // masterclass|counselling|bootcamp|contact|waitlist
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('course_slug')->nullable();
            $table->text('message')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('page')->nullable();
            $table->string('ip', 45)->nullable();
            // DPDP: consent checkbox is required; we log when + which policy version.
            $table->timestamp('consented_at');
            $table->string('consent_version')->default('v1');
            $table->boolean('crm_synced')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('lead_type');
            $table->index('created_at');
            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
