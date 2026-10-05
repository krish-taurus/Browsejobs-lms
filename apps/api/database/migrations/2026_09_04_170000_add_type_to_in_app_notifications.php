<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the student Alerts page show only a chosen subset of notification
     * kinds (Sept 2026) — "class" and "chat" for now, more later (see
     * config('notifications.visible_types'), set via
     * `php artisan notifications:visible-types`). Null/uncategorised rows
     * (most existing creation sites — badges, reports, placement, mock,
     * care/admin alerts) simply don't match any filter and stay hidden
     * until someone tags their source with a type.
     */
    public function up(): void
    {
        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
