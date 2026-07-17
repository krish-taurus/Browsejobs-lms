<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pool of Zoom host licenses (PRD §6.3). One Zoom user can host only one meeting at a
 * time, so running concurrent classes needs several licensed hosts under the account.
 * Each license is allocated to a mentor/trainer; a batch's classes are then created
 * under that mentor's Zoom user, so two mentors can teach at once without clashing.
 *
 * Also adds `auto_record` to live_sessions — classes record to the cloud by default, and
 * a session can opt out at schedule time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('zoom_user_id');        // the Zoom user/email meetings are hosted under
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            // A license is held by at most one mentor at a time.
            $table->unique(['tenant_id', 'mentor_id']);
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->boolean('auto_record')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('auto_record');
        });
        Schema::dropIfExists('zoom_licenses');
    }
};
