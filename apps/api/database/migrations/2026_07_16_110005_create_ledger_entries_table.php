<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-student fee ledger (PRD §6.8 "per-student ledger"). A debit is written
     * per instalment when a plan is created (amount owed); a credit when a
     * payment is captured. Outstanding balance = debits − credits.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');   // debit|credit
            $table->unsignedBigInteger('amount_paise');
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->index('fee_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
