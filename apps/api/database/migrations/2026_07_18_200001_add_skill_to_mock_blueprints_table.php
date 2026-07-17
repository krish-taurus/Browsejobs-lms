<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A skill tag on a mock blueprint (PRD §6.6) so a course can have several targeted
 * mocks — a "Python" mock and a "SQL" mock — rather than one generic role mock. Null
 * for the untagged general blueprint that pre-dates this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_blueprints', function (Blueprint $table) {
            $table->string('skill')->nullable()->after('role_title');
        });
    }

    public function down(): void
    {
        Schema::table('mock_blueprints', function (Blueprint $table) {
            $table->dropColumn('skill');
        });
    }
};
