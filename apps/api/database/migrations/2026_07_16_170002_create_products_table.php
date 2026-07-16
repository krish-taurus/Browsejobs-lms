<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sellable product catalog (PRD §6.17): credit packs, the Career+
     * subscription, and self-paced recorded courses. Admin-CRUD, seeded with the
     * PRD defaults; self-paced products are created on publish (source_batch_id).
     * All money in paise.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('feature')->nullable();                               // EntitlementFeature | career_plus | self_paced
            $table->string('kind');                                              // pack|subscription|self_paced|upgrade
            $table->unsignedBigInteger('price_paise');
            $table->unsignedInteger('grant_amount')->default(0);                 // credits granted per purchase
            $table->unsignedInteger('period_days')->nullable();                  // subscription cycle
            $table->foreignId('source_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
