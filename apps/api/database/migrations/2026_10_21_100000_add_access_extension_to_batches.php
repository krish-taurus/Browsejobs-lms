<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A whole batch's students kept in class past the fee grace period, with the
     * date it ends and why. Set from the CRM's Live Batches page ("Extend access");
     * the per-student extension on a fee plan stays for one-off cases.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->date('access_extended_until')->nullable();
            $table->string('access_extended_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->dropColumn(['access_extended_until', 'access_extended_reason']);
        });
    }
};
