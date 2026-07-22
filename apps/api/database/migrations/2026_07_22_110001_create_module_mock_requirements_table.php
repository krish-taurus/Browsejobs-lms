<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One student's mock quota for one module (PRD §6.6). Opened when the student
 * completes the module; advanced FIFO as they finish mocks, and cleared once
 * `completed` reaches `required`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_mock_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('required');
            $table->unsignedTinyInteger('completed')->default(0);
            $table->timestamp('unlocked_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_mock_requirements');
    }
};
