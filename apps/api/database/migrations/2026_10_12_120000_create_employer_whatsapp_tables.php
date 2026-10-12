<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post jobs on WhatsApp (hiring pipeline step 1).
 *
 * A workspace member opens the employer portal and sees a QR code. Scanning
 * it opens WhatsApp with "JOIN BJ-XXXXXX" ready to send; sending it links
 * that phone to the workspace, acting as that member. From then on the
 * client describes a role in plain words, the agent drafts the JD, and it
 * goes live only when the client replies YES.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_whatsapp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['employer_workspace_id', 'user_id']);
        });

        Schema::create('employer_whatsapp_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_workspace_id')->constrained()->cascadeOnDelete();
            // The member this phone acts as: jobs it posts are theirs.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Country code + number, digits only. One workspace per phone.
            $table->string('phone', 20)->unique();
            $table->string('profile_name')->nullable();
            $table->string('state', 20)->default('idle'); // idle | confirming
            $table->json('draft')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();

            $table->index('employer_workspace_id');
        });

        Schema::create('employer_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employer_whatsapp_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('direction', 3); // in | out
            $table->text('body');
            // Meta retries a webhook it thinks failed; the id stops a second reply.
            $table->string('wamid', 191)->nullable()->unique();
            $table->string('status', 20)->default('received'); // received | processed | sent | failed
            $table->timestamps();

            $table->index(['employer_whatsapp_link_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_whatsapp_messages');
        Schema::dropIfExists('employer_whatsapp_links');
        Schema::dropIfExists('employer_whatsapp_codes');
    }
};
