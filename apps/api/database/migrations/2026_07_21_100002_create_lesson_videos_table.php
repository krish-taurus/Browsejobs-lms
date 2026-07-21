<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source'); // VideoSource
            $table->string('title')->nullable();
            $table->text('url')->nullable();                 // source=url
            $table->foreignId('recording_id')->nullable()->constrained()->nullOnDelete(); // source=recording
            $table->string('path')->nullable();              // source=upload (s3 key)
            $table->timestamps();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_videos');
    }
};
