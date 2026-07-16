<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Period-scoped usage of an included quota (PRD §6.17) — e.g. voice mocks this
     * course, AI tokens today. Phase-4 consumers write here via the Entitlement
     * Service; P2.8 records consumption drawn against wallets.
     */
    public function up(): void
    {
        Schema::create('quota_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->string('period_key');                                        // e.g. 2026-07, batch:12, all-time
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('limit_amount')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'user_id', 'feature', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_usages');
    }
};
