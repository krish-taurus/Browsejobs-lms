<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            // Bumped whenever the schedule changes; reminder jobs carrying a
            // stale token no-op, which is how the ladder is "cancelled".
            $table->string('reminder_token')->nullable()->after('status');
        });

        Schema::create('session_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // cancelled | rescheduled
            $table->string('reason')->nullable();
            $table->timestamp('old_start')->nullable();
            $table->timestamp('new_start')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('live_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_changes');
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn('reminder_token');
        });
    }
};
