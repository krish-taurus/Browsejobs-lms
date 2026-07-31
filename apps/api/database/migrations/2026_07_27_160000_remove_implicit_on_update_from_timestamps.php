<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MariaDB (XAMPP default) gives the FIRST timestamp column of a table an
     * implicit ON UPDATE CURRENT_TIMESTAMP. That silently corrupts values that
     * must never move on unrelated updates — a session's scheduled_start was
     * being reset to "now" whenever the reminder token was re-armed. Redefining
     * the columns without the ON UPDATE clause removes the behaviour. MySQL 8
     * (production) and sqlite (tests) don't exhibit it, but the statements are
     * harmless there, so guard only on driver.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE live_sessions MODIFY scheduled_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE leads MODIFY consented_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE mock_interviews MODIFY started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE topic_completions MODIFY completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // Intentionally left in place — the implicit ON UPDATE behaviour is a
        // defect, not a feature to restore.
    }
};
