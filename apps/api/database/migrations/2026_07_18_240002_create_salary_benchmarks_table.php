<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated salary benchmark dataset (PRD §6.21) for offer-evaluation and
 * negotiation guidance per role / city / experience band. Reference data
 * maintained by the placement team, refreshed quarterly. All figures are annual
 * CTC in paise (integers, per CLAUDE.md — never floats for currency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_title');
            $table->string('city');
            $table->string('experience_band'); // fresher | 1-3 | 3-5 | 5plus
            $table->unsignedBigInteger('p25_ctc_paise');
            $table->unsignedBigInteger('p50_ctc_paise');
            $table->unsignedBigInteger('p75_ctc_paise');
            $table->string('source')->nullable();
            $table->date('effective_on')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'role_title', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_benchmarks');
    }
};
