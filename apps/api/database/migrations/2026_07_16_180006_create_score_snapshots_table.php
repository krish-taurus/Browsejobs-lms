<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A daily snapshot of a student's scores (PRD §6.4) — powers the PRI trajectory
     * on the Coach Panel and the "daily movers" on the counselor risk dashboard.
     * One row per student per day.
     */
    public function up(): void
    {
        Schema::create('score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedTinyInteger('pri')->default(0);
            $table->unsignedTinyInteger('engagement')->default(0);
            $table->unsignedTinyInteger('risk_dropout')->default(0);
            $table->unsignedTinyInteger('risk_placement')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'user_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_snapshots');
    }
};
