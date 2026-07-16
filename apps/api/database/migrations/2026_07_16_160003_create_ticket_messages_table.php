<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One message in a ticket thread (PRD §6.13). `author_type` = student|staff|
     * system; `is_internal` marks a staff-only note; `channel` records where the
     * message came from (portal|whatsapp|email) for two-way threading.
     */
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type');                                       // student|staff|system
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->string('channel')->default('portal');                        // portal|whatsapp|email
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
