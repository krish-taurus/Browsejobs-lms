<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.4 follow-up: mentors are scoped to the courses they cover, so a Data
 * Engineering student never sees the DevOps mentor's slots. Null/empty =
 * generalist (visible to every course).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table): void {
            $table->json('course_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table): void {
            $table->dropColumn('course_ids');
        });
    }
};
