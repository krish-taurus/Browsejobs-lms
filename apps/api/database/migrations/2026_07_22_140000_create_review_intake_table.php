<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drive-synced review images awaiting admin triage (Platform Spec §3). The
 * scheduled sync pulls each new image from the reviews folder into here as
 * `pending`; an admin then publishes it (as a review or placement story) or
 * dismisses it. `drive_file_id` dedups so a file is never imported twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_intake', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('file_name');
            $table->string('image_path');            // stored on s3
            $table->string('status')->default('pending'); // pending | published | dismissed
            $table->string('published_kind')->nullable(); // review | story
            $table->unsignedBigInteger('published_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'drive_file_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_intake');
    }
};
