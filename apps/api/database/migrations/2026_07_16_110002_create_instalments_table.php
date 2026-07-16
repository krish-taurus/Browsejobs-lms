<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One scheduled instalment of a fee plan (PRD §6.8). Instalment 1 is due at
     * checkout, the rest same calendar day monthly. Carries the Razorpay order /
     * personalized payment-link references.
     */
    public function up(): void
    {
        Schema::create('instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->unsignedBigInteger('amount_paise');
            $table->date('due_on');
            $table->string('status')->default('pending');   // pending|paid|failed
            $table->timestamp('paid_at')->nullable();
            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_link_id')->nullable();
            $table->text('payment_link_url')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status', 'due_on']);
            $table->index('razorpay_order_id');
            $table->index('razorpay_payment_link_id');
            $table->unique(['fee_plan_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalments');
    }
};
