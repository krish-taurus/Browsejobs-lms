<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Message templates (PRD §6.9 template manager). One row per (key, channel):
     * a `{{var}}` body, an optional WhatsApp Meta-approved template name, and a
     * utility|marketing category driving the send guards + banned-phrase linter.
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('channel');        // whatsapp|email|inapp
            $table->string('category')->default('utility'); // utility|marketing
            $table->string('name')->nullable();   // WhatsApp approved template name
            $table->string('subject')->nullable(); // email
            $table->text('body');
            $table->string('locale', 8)->default('en');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
