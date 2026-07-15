<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Students authenticate by OTP and have no password.
            $table->string('password')->nullable()->change();

            $table->string('phone')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('user_type')->default('student')->after('phone');
            $table->boolean('two_factor_enabled')->default(false)->after('user_type');

            $table->unique(['tenant_id', 'phone']);
            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'phone']);
            $table->dropIndex(['user_type']);
            $table->dropColumn(['phone', 'phone_verified_at', 'user_type', 'two_factor_enabled']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
