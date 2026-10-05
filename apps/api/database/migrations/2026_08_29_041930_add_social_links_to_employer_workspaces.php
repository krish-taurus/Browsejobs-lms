<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an employer lives online, beyond their website.
 *
 * Ops asks for these when a company is signed up — a LinkedIn page is how you
 * check a company is real before you put their logo on a job board, and it is
 * where candidates go to look them up. Held as JSON because the set of
 * networks that matter changes faster than a schema should.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_workspaces', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('employer_workspaces', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
