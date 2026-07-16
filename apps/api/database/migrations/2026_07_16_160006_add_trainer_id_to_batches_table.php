<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The trainer assigned to a batch (PRD §6.13 "Training auto-resolves to the
     * student's batch trainer"). A plain nullable indexed column with no DB FK — an
     * ALTER can't add a foreign key on SQLite (test DB); the app treats it as a
     * users.id reference.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_id')->nullable()->after('status');
            $table->index('trainer_id');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['trainer_id']);
            $table->dropColumn('trainer_id');
        });
    }
};
