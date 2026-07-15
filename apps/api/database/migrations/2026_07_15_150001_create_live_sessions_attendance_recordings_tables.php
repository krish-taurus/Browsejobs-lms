<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end')->nullable();
            // Zoom identifiers. Raw join/start URLs are never exposed via the API;
            // students reach the meeting only through the gated Join endpoint.
            $table->string('zoom_meeting_id')->nullable();
            $table->text('zoom_join_url')->nullable();
            $table->text('zoom_start_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('batch_id');
            $table->index('zoom_meeting_id');
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_joined_at')->nullable();
            $table->timestamp('last_left_at')->nullable();
            // Set while a participant is currently in the meeting; cleared on leave.
            $table->timestamp('open_join_at')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);
            $table->unsignedTinyInteger('attended_pct')->default(0);
            $table->boolean('is_late')->default(false);
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('override_reason')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['live_session_id', 'user_id']);
        });

        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // S3/MinIO object key; null until the pull job stores it.
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('live_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('live_sessions');
    }
};
