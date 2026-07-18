<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DPDP Act 2023 data-subject requests (PRD §8 / §10 — launch blocker): a student
 * can request a copy of their data (access) or its erasure (deletion). A staff
 * grievance officer processes each; `export` caches the compiled access payload.
 * `anonymized_at` marks a user whose PII has been scrubbed (records retained for
 * legal/financial obligations, PII removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');   // access | deletion
            $table->string('status')->default('pending'); // pending | completed | rejected
            $table->string('reason')->nullable();
            $table->json('export')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
        Schema::dropIfExists('data_requests');
    }
};
