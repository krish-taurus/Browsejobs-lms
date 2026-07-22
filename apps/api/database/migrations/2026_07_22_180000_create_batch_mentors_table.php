<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mentors allocated to a batch (PRD §6.3). Mentors otherwise only do per-student
 * bookings; this links them to a batch so they're notified about batch changes
 * alongside the trainers and admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_mentors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'user_id']);
            $table->index(['tenant_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_mentors');
    }
};
