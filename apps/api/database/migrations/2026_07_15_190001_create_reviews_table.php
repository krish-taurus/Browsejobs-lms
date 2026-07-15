<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public reviews wall (spec §6.1 /reviews). Real reviews are imported from
     * Google/WhatsApp exports via `php artisan reviews:import` — never
     * fabricated.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('author_name');
            $table->string('course_slug')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('source'); // google | whatsapp | platform
            $table->date('reviewed_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'source']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
