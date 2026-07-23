<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview Kit unlocks (ADR 0048): one row = this user unlocked this posting's
 * full prep (question paper + JD mocks). Spent from `job_kit` wallet credits;
 * enrolled students and Career+ subscribers never need a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_kit_unlocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_feed_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'job_feed_item_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_kit_unlocks');
    }
};
