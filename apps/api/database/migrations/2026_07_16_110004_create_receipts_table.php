<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A branded GST tax invoice for a captured payment (PRD §6.8). GST is
     * inclusive: total_paise = taxable + cgst + sgst. `number` is a per-tenant
     * sequential receipt number. `storage_path` points at the rendered document.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->unsignedBigInteger('taxable_paise');
            $table->unsignedBigInteger('cgst_paise');
            $table->unsignedBigInteger('sgst_paise');
            $table->unsignedBigInteger('total_paise');
            $table->string('storage_path')->nullable();
            $table->string('status')->default('pending');   // pending|rendered
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
