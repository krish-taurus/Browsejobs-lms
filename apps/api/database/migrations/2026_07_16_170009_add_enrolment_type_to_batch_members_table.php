<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes a self-paced enrolment from a live one (PRD §6.3). Self-paced
     * members get recordings but cannot join live sessions. Plain column with a
     * default; no ALTER-add-FK needed.
     */
    public function up(): void
    {
        Schema::table('batch_members', function (Blueprint $table) {
            $table->string('enrolment_type')->default('live')->after('status');
            $table->index('enrolment_type');
        });
    }

    public function down(): void
    {
        Schema::table('batch_members', function (Blueprint $table) {
            $table->dropIndex(['enrolment_type']);
            $table->dropColumn('enrolment_type');
        });
    }
};
