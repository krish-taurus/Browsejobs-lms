<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's fee plan for a paid batch (PRD §6.8 / §14.4). Registration
     * ₹30,000 as a single payment or EMI 1/2/3. All money in paise integers,
     * server-owned. `discount_paise` (optional, applied to instalment 1) stands
     * in for the deferred voucher manager.
     */
    public function up(): void
    {
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // student
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->string('type');                                              // single|emi
            $table->unsignedBigInteger('total_paise');
            $table->unsignedBigInteger('discount_paise')->default(0);
            $table->unsignedSmallInteger('gst_rate_bps')->default(1800);
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('active');                         // draft|active|paid|cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plans');
    }
};
