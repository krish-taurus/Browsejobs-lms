<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite rewrites inbound foreign keys when a table is renamed, and
     * PRAGMA foreign_keys cannot change inside the transaction Laravel wraps
     * around a migration. Opt out so the rebuild below can copy celebrations
     * without leaving celebration_guidances pointed at a dropped table.
     */
    public $withinTransaction = false;

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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->makeStudentIdNullableOnSqlite();
        } else {
            DB::statement('ALTER TABLE celebrations MODIFY student_id BIGINT UNSIGNED NULL');
        }

        Schema::table('celebrations', function (Blueprint $table) {
            $table->string('student_name')->nullable()->after('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropColumn('student_name');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE celebrations MODIFY student_id BIGINT UNSIGNED NOT NULL');
        }
    }

    /**
     * SQLite cannot MODIFY a column. Copy into a new table with a nullable
     * student_id, then swap names. Inbound FKs (celebration_guidances) keep
     * saying "celebrations" because the live table is never renamed first.
     */
    private function makeStudentIdNullableOnSqlite(): void
    {
        DB::unprepared('PRAGMA foreign_keys = OFF');
        DB::unprepared('DROP INDEX IF EXISTS celebrations_tenant_id_published_at_index');

        Schema::create('celebrations_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('display_mode');
            $table->string('anonymous_label')->nullable();
            $table->string('role_title');
            $table->string('company')->nullable();
            $table->timestamp('consented_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('photo_path')->nullable();
            $table->string('video_url')->nullable();
            $table->index(['tenant_id', 'published_at']);
        });

        DB::statement('INSERT INTO celebrations_new (id, tenant_id, student_id, display_mode, anonymous_label, role_title, company, consented_at, created_by, published_at, is_active, created_at, updated_at, photo_path, video_url)
            SELECT id, tenant_id, student_id, display_mode, anonymous_label, role_title, company, consented_at, created_by, published_at, is_active, created_at, updated_at, photo_path, video_url FROM celebrations');

        Schema::drop('celebrations');
        Schema::rename('celebrations_new', 'celebrations');
        DB::unprepared('PRAGMA foreign_keys = ON');
    }
};
