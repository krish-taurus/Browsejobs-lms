<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Company attribution for real-interview uploads (founder: rank what companies focus on). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_transcripts', function (Blueprint $table): void {
            $table->string('company')->nullable()->after('role_title');
        });
    }

    public function down(): void
    {
        Schema::table('interview_transcripts', function (Blueprint $table): void {
            $table->dropColumn('company');
        });
    }
};
