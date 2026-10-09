<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records why a staff alert did not go out. Nullable, so existing rows
     * and a mail outage both leave the enquiry itself intact.
     */
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->text('notify_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('notify_error');
        });
    }
};
