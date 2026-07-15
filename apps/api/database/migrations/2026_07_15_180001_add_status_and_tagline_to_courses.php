<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform Spec v1.0 §6.1: courses are either live or coming_soon
     * (waitlist), and carry a marketing tagline.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('status')->default('live')->after('slug');
            $table->string('tagline')->nullable()->after('status');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'tagline']);
        });
    }
};
