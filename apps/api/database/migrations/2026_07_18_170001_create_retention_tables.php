<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.6a — review protection & retention (PRD §6.20): NPS pulses with
 * promoter/detractor routing, rage-signal intervention alerts, pause/defer
 * enrolment, and the week-1 white-glove onboarding checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nps_pulses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            // week1 | mid | pre_placement
            $table->string('milestone', 20);
            $table->unsignedTinyInteger('score')->nullable(); // null = pending
            $table->string('comment', 1000)->nullable();
            // none | google_review | rescue — set at submit time
            $table->string('routed', 20)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'milestone']);
        });

        Schema::create('intervention_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->json('signals');
            // open | handled
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('enrolment_pauses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('batch_member_id');
            $table->string('reason', 1000);
            // requested | approved | rejected | resumed
            $table->string('status', 20)->default('requested')->index();
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->date('paused_until')->nullable();
            $table->foreignId('resume_batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->unique();
            $table->timestamp('welcome_call_at')->nullable();
            $table->timestamp('platform_tour_at')->nullable();
            $table->timestamp('week1_checkin_at')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_checklists');
        Schema::dropIfExists('enrolment_pauses');
        Schema::dropIfExists('intervention_alerts');
        Schema::dropIfExists('nps_pulses');
    }
};
