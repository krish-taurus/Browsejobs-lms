<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prices for students outside India.
     *
     * India keeps using `courses.fee_paise` — nothing about the rupee price
     * changes. This table holds the other currencies, one row per region, so a
     * second currency never means a second column on `courses`. Today the CRM
     * only writes the 'INTL' row (everyone outside India); per-country rows
     * ('US', 'AE', …) fit the same shape when they are wanted.
     *
     * Amounts are in the currency's minor unit (cents for USD), matching how
     * fee_paise already stores rupees, so no float ever touches money.
     */
    public function up(): void
    {
        Schema::create('course_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            /** 'INTL' = anywhere outside India; otherwise an ISO country code. */
            $table->string('region', 12)->default('INTL');
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedTinyInteger('emi_count')->nullable();
            $table->unsignedBigInteger('emi_amount_minor')->nullable();
            $table->timestamps();

            // One price per course per region; re-saving updates in place.
            $table->unique(['course_id', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prices');
    }
};
