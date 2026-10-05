<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A friendly name and WhatsApp number for the person being invited.
 *
 * Neither ever becomes the invitee's real profile — that is always self-
 * entered when they accept, by design (AcceptEmployerInvite takes an already
 * -authenticated user, never a name typed by someone else). These two columns
 * exist purely so the owner's own pending-invites list reads as "Priya Sharma
 * (pending)" instead of a bare email address, and so the WhatsApp number they
 * already have for that person is not lost between inviting and following up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_invites', function (Blueprint $table) {
            $table->string('name')->nullable()->after('email');
            $table->string('whatsapp', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('employer_invites', function (Blueprint $table) {
            $table->dropColumn(['name', 'whatsapp']);
        });
    }
};
