<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client can post a job by voice note: the WhatsApp media id is kept on
 * the inbound message so the agent can download and transcribe it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_whatsapp_messages', function (Blueprint $table) {
            $table->string('media_id', 64)->nullable()->after('wamid');
        });
    }

    public function down(): void
    {
        Schema::table('employer_whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('media_id');
        });
    }
};
