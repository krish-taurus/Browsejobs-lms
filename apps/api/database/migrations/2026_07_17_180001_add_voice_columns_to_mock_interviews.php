<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.3 — voice mocks (PRD §6.6/§6.17). Voice sessions reuse mock_interviews
 * (mode='voice', ADR 0029); these columns tie a row to its provider call and
 * record per-session duration + cost for margin tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->string('provider_session_id')->nullable()->index();
            $table->string('join_url', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('cost_micros')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mock_interviews', function (Blueprint $table): void {
            $table->dropColumn(['provider_session_id', 'join_url', 'duration_seconds', 'cost_micros']);
        });
    }
};
