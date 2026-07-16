<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-way messaging reply signal (PRD §6.12 "auto-pause on reply", deferred
     * to P2.4 in ADR 0006). The WhatsApp inbound webhook stamps this; P2.5's
     * nudge ladders read it to pause a sequence when the lead replies.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('last_replied_at')->nullable()->after('sla_breached_at');
            $table->index('last_replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['last_replied_at']);
            $table->dropColumn('last_replied_at');
        });
    }
};
