<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-module mock target (PRD §6.6 "N mocks per module"). Null falls back to the
 * tenant/config default (config('mocks.per_module')), so existing modules keep
 * working without a value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('required_mocks')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->dropColumn('required_mocks');
        });
    }
};
