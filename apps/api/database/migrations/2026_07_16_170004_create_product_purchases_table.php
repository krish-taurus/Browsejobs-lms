<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An in-app purchase (PRD §6.17). Settled by the Razorpay webhook: on capture
     * it grants the product (credits / access / subscription cycle) and carries its
     * own inclusive-GST breakup + receipt (decoupled from the fee receipts table).
     * All money in paise.
     */
    public function up(): void
    {
        Schema::create('product_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku');
            $table->string('feature')->nullable();
            $table->string('kind');
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('taxable_paise')->default(0);
            $table->unsignedBigInteger('cgst_paise')->default(0);
            $table->unsignedBigInteger('sgst_paise')->default(0);
            $table->unsignedBigInteger('total_paise')->default(0);
            $table->string('status')->default('pending');                        // pending|captured|failed|refunded
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id']);
            $table->index('razorpay_order_id');
            $table->index(['tenant_id', 'status', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchases');
    }
};
