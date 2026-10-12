<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp's own in-app scanner rejects a plain wa.me QR as "Invalid QR
 * code" — it only accepts QR codes WhatsApp created. Each join code now also
 * gets a Meta "message QR" (wa.me/message/XXXX with JOIN <code> prefilled),
 * which opens from WhatsApp's scanner and from the phone camera alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_whatsapp_codes', function (Blueprint $table) {
            $table->string('meta_qr_code', 64)->nullable()->after('code');
            $table->string('meta_deep_link')->nullable()->after('meta_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('employer_whatsapp_codes', function (Blueprint $table) {
            $table->dropColumn(['meta_qr_code', 'meta_deep_link']);
        });
    }
};
