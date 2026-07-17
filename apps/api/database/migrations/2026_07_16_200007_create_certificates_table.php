<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A course-completion certificate (PRD §6.5). Auto-issued when a student finishes
     * a course, or manually by staff. `code` is a globally-unique, unguessable token
     * so the QR verification page works from any host without tenant resolution;
     * `number` is a per-tenant serial. `storage_path` points at the rendered document.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('number');
            $table->string('title');
            $table->timestamp('issued_at')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('status')->default('pending');    // pending|rendered
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code');                          // global — host-independent verify
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'user_id', 'course_id']); // one cert per student+course
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
