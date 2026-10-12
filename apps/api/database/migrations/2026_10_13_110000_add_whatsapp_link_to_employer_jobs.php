<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job posted from WhatsApp remembers that chat, so the shortlist agent
 * can send its report back there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_jobs', function (Blueprint $table) {
            $table->foreignId('whatsapp_link_id')->nullable()->after('shortlist_settings')
                ->constrained('employer_whatsapp_links')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employer_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_link_id');
        });
    }
};
