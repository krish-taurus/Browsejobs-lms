<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invite nobody answered for a day is offered again once, with fresh slots
 * (interviews:remind). chased_at records that, so it is never done twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->timestamp('chased_at')->nullable()->after('nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->dropColumn('chased_at');
        });
    }
};
