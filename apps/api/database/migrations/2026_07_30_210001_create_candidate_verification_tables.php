<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate verification (PRD-E F9). One row per candidate per check kind,
 * so the dashboard can show what is done, what is pending and what is still
 * untouched without inventing state.
 *
 * `provider` names which transport produced the result — `manual` today,
 * a DigiLocker/EPFO/BGV vendor later — and `provider_ref` is that vendor's
 * own identifier for the case. No provider credentials live here.
 *
 * `evidence` deliberately stores only what an employer is entitled to see
 * (the fact of a match, the issuer, the period covered) — never the raw
 * document payload, which stays in object storage under the existing
 * media path and is not exposed by the employer-facing resources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_verification_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');                       // identity | education | employment | documents
            $table->string('status')->default('not_started');
            $table->string('provider')->nullable();       // manual | digilocker | epfo | <vendor>
            $table->string('provider_ref')->nullable();
            $table->json('evidence')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One standing result per kind per candidate; re-checks overwrite.
            $table->unique(['user_id', 'kind']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_verification_checks');
    }
};
