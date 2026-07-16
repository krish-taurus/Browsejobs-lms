<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified message log (PRD §6.9 delivery/read logging + §8 `messages`).
     * Outbound sends and inbound WhatsApp replies both land here. Status walks
     * queued → sent → delivered → read (or failed/suppressed/blocked).
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction')->default('out'); // out|in
            $table->string('channel');                    // whatsapp|email|inapp
            $table->string('template_key')->nullable();
            $table->string('category')->default('utility');
            $table->string('recipient')->nullable();      // phone or email
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('queued');  // queued|sent|delivered|read|failed|suppressed|blocked
            $table->string('suppressed_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
