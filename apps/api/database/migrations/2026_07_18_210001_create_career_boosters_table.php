<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Career+ job-probability boosters (PRD §6.20, P4.6b): the latest generated LinkedIn
 * optimisation, GitHub portfolio, and interview-day prep pack per student. Kept as the
 * latest per kind so the student can revisit without re-spending tokens; regenerating
 * overwrites. `content_source` records ai vs the deterministic fallback, like CVs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_boosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');                 // linkedin | github | prep_pack
            $table->json('content');
            $table->string('content_source')->default('ai'); // ai | fallback
            $table->timestamps();

            $table->unique(['user_id', 'kind']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_boosters');
    }
};
