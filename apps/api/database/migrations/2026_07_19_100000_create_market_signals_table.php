<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Platform-global market-intelligence snapshots (like platform_settings, no
// tenant_id): derived from public news/job-feed aggregates, never tenant data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_signals', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32); // city_pulse | funding | outlook
            $table->json('payload');
            $table->date('effective_on');
            $table->string('source', 64)->default('seed');
            $table->timestamps();
            $table->unique(['kind', 'effective_on']);
            $table->index('effective_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_signals');
    }
};
