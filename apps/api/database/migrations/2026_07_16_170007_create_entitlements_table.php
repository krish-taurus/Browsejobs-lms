<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A non-countable access grant (PRD §6.17): `self_paced:{batchId}`,
     * `live:{batchId}`, or `career_plus`. `expires_at` null = perpetual.
     */
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('kind');
            $table->string('status')->default('active');                         // active|expired|revoked
            $table->foreignId('source_purchase_id')->nullable()->constrained('product_purchases')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
