<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's save/dismiss state on a feed item (PRD §6.22). One row per
 * (student, item): saved items pin to the top, dismissed items drop out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_feed_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_feed_item_id')->constrained()->cascadeOnDelete();
            $table->string('state'); // saved | dismissed
            $table->timestamps();

            $table->unique(['user_id', 'job_feed_item_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_feed_saves');
    }
};
