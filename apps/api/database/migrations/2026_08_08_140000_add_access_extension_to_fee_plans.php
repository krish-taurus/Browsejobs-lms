<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-student reprieve on the dunning ladder. When the academic team agrees to
 * give someone a few more days ("salary comes on the 5th"), the ladder must stop
 * blocking them without the fee itself being written off — so the extension
 * lives on the plan, dated, rather than as a silent lift of the block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_plans', function (Blueprint $table): void {
            $table->date('access_extended_until')->nullable()->after('status');
            $table->string('access_extended_reason')->nullable()->after('access_extended_until');
        });
    }

    public function down(): void
    {
        Schema::table('fee_plans', function (Blueprint $table): void {
            $table->dropColumn(['access_extended_until', 'access_extended_reason']);
        });
    }
};
