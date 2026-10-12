<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An L1/L2 slot booked on WhatsApp becomes a Zoom meeting straight away, and
 * a meeting bot is booked to join it and run the AI interviewer:
 *  - scheduled_at: the slot (day + time) the candidate picked;
 *  - zoom_meeting_id / zoom_join_url: the meeting sent to the candidate;
 *  - bot_code: the private key in the interviewer page the bot opens;
 *  - bot_id / bot_status: the provider's bot and where it stands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('chosen_date');
            $table->string('zoom_meeting_id', 32)->nullable()->after('scheduled_at');
            $table->string('zoom_join_url', 512)->nullable()->after('zoom_meeting_id');
            $table->string('bot_code', 64)->nullable()->unique()->after('zoom_join_url');
            $table->string('bot_id', 64)->nullable()->after('bot_code');
            $table->string('bot_status', 30)->nullable()->after('bot_id');
        });
    }

    public function down(): void
    {
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->dropUnique(['bot_code']);
            $table->dropColumn(['scheduled_at', 'zoom_meeting_id', 'zoom_join_url', 'bot_code', 'bot_id', 'bot_status']);
        });
    }
};
