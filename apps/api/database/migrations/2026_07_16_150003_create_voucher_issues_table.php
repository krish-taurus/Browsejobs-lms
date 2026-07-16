<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A voucher issued to one student (PRD §5 Stage 3 / §6.8). One row per
     * issuance drives redemption analytics + usage-limit enforcement. When applied
     * it feeds `fee_plan.discount_paise` (the pre-apply seam). Money in paise.
     */
    public function up(): void
    {
        Schema::create('voucher_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();      // student
            $table->foreignId('testimonial_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('issued');                         // issued|applied|expired|void
            $table->unsignedBigInteger('discount_paise')->nullable();            // computed at apply time
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_issues');
    }
};
