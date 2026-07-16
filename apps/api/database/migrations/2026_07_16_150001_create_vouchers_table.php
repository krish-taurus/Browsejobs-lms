<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configured voucher definitions (PRD §6.8 voucher manager). One flagged
     * `is_review_reward` voucher is auto-issued on testimonial approval (§5 Stage
     * 3). `value` is paise for a flat voucher, basis points for a percent voucher.
     * All money in paise integers, server-owned.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();                                  // optional campaign code
            $table->string('name');
            $table->string('type');                                              // flat|percent
            $table->unsignedBigInteger('value');                                 // paise (flat) or bps (percent)
            $table->unsignedBigInteger('max_discount_paise')->nullable();        // cap for percent
            $table->unsignedInteger('valid_days')->nullable();                   // expiry window from issue
            $table->date('valid_until')->nullable();                             // absolute expiry
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete(); // null = all courses
            $table->unsignedInteger('usage_limit')->nullable();                  // total issuances (null = unlimited)
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('allow_stacking')->default(false);
            $table->boolean('is_review_reward')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'active', 'is_review_reward']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
