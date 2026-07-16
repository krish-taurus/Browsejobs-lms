<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app dashboard notifications (PRD §6.9 "dashboard notifications"). Named
     * `in_app_notifications` to avoid colliding with Laravel's Notifiable
     * `notifications` table (different schema). Each carries a one-tap deep link.
     */
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->text('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
    }
};
