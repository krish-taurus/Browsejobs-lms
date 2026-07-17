<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform integration settings (LLM / WhatsApp / Vapi / Zoom credentials), editable in
 * the admin panel by a super-admin.
 *
 * Platform-global by design — one set of keys for the whole deployment — so there is no
 * `tenant_id`. Values are encrypted at rest via the model's `encrypted` cast; the
 * whitelist in config/platform_settings.php is the only thing that may be stored or
 * applied. What each value overrides at runtime lives there, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');           // ai | whatsapp | vapi | zoom
            $table->string('key');
            $table->text('value')->nullable(); // encrypted
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
