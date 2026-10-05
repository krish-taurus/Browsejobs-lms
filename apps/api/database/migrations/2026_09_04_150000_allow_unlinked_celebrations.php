<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A success story someone wants published for a real person who isn't
     * (yet, or ever will be) an LMS account — an older alum, someone from
     * before this system existed — needn't be blocked from the wall just
     * because there's no student_id to point at. student_name carries the
     * name in that case; when student_id IS set, displayName() still
     * prefers the live account name over it, same as before.
     *
     * Raw SQL rather than Schema::table()->change() — this app doesn't
     * carry doctrine/dbal, which that fluent method needs under the hood.
     * The FK stays; MySQL foreign keys allow NULL on a nullable column
     * without touching the constraint itself.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE celebrations MODIFY student_id BIGINT UNSIGNED NULL');

        Schema::table('celebrations', function (Blueprint $table) {
            $table->string('student_name')->nullable()->after('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropColumn('student_name');
        });

        DB::statement('ALTER TABLE celebrations MODIFY student_id BIGINT UNSIGNED NOT NULL');
    }
};
