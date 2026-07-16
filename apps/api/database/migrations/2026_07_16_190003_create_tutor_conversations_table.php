<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's AI Tutor conversation thread (PRD §6.4). `lesson_id` is set when
     * the conversation is scoped to a coding lab (hint-ladder context).
     */
    public function up(): void
    {
        Schema::create('tutor_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'student_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_conversations');
    }
};
