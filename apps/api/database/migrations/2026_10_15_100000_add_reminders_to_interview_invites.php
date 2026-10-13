<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview reminders (interviews:remind): when the candidate was reminded
 * about an hour before their slot, and when they were nudged because they had
 * not started half an hour after it. Each is sent once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('opened_at');
            $table->timestamp('nudged_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'nudged_at']);
        });
    }
};
