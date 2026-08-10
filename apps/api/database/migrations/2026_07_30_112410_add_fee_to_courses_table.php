<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-course registration fee (paise). Null = the tenant-wide default from
 * config/fees.php (FEE_REGISTRATION_PAISE) — so existing courses keep the
 * global ₹30,000 until a fee is set explicitly (founder request, Jul 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('fee_paise')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('fee_paise');
        });
    }
};
