<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - employer_whatsapp_links.choice: a multi-step question Taurus is asking the client
     *   in the chat ("which role?", then "which candidates?"), so the next message answers
     *   it instead of being read as a new job.
     * - employer_interviews.previous_attempts: what a retaken round looked like before it
     *   was reset (score, recording id, when), so a retake never erases the first result.
     */
    public function up(): void
    {
        Schema::table('employer_whatsapp_links', function (Blueprint $table): void {
            $table->json('choice')->nullable();
        });

        Schema::table('employer_interviews', function (Blueprint $table): void {
            $table->json('previous_attempts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employer_whatsapp_links', function (Blueprint $table): void {
            $table->dropColumn('choice');
        });

        Schema::table('employer_interviews', function (Blueprint $table): void {
            $table->dropColumn('previous_attempts');
        });
    }
};
