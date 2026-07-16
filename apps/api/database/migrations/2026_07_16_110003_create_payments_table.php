<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Razorpay payment attempt against an instalment (PRD §6.8). The webhook is
     * the source of truth; `razorpay_payment_id` is unique so a duplicate
     * `payment.captured` reconciles idempotently.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instalment_id')->constrained()->cascadeOnDelete();
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->string('status')->default('created');   // created|captured|failed|refunded
            $table->string('method')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('razorpay_order_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
