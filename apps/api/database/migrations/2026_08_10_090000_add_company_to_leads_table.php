<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employer enquiries from the /employers landing page arrive as
     * `lead_type = employer` and carry the hiring company's name. Counselors
     * work them on the same CRM pipeline, so the name has to be a first-class
     * column rather than buried in the free-text message.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('company', 190)->nullable()->after('course_slug');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }
};
