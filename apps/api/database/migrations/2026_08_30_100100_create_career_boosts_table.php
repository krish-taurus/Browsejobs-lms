<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per purchased Career Boost — the running counters checked at
 * StartEmployerJobMock, ApplyToEmployerJob and JobsForYou. A candidate can
 * hold more than one unexpired boost (buying a second before the first runs
 * out): their remaining bonuses simply add up, consumed from whichever row
 * expires soonest first (see ActiveCareerBoost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_boosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_purchase_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('mock_bonus_total')->default(0);
            $table->unsignedInteger('mock_bonus_used')->default(0);
            $table->unsignedInteger('job_application_bonus_total')->default(0);
            $table->unsignedInteger('job_application_bonus_used')->default(0);
            $table->unsignedInteger('wider_market_job_limit')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_boosts');
    }
};
