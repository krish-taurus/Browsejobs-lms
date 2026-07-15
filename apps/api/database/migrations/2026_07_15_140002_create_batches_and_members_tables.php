<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // {COURSE}-{YYYYMM}-{seq}, e.g. DE-202608-01. Unique within a tenant.
            $table->string('number');
            $table->string('type');
            $table->unsignedInteger('capacity')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // A paid batch can be linked to the bootcamp that feeds it (Stage 3).
            $table->foreignId('linked_source_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('course_id');
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('batch_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('reserved');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('batch_id');
            $table->index('user_id');
            $table->unique(['batch_id', 'user_id']);
        });

        Schema::create('topic_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_completions');
        Schema::dropIfExists('batch_members');
        Schema::dropIfExists('batches');
    }
};
