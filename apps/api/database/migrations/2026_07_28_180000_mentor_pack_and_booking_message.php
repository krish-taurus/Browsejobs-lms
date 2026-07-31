<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mentoring commercial model: every student starts with 3 free 1:1
     * sessions (seeded by the EntitlementService); the ₹499 top-up now grants
     * a 3-session pack. The booking confirmation gets the branded rich
     * format (detail-card email lines + emoji WhatsApp copy).
     */
    public function up(): void
    {
        DB::table('products')
            ->where('feature', 'mentor')
            ->update([
                'name' => '3 Mentor Sessions Pack',
                'grant_amount' => 3,
                'updated_at' => now(),
            ]);

        DB::table('message_templates')
            ->where('key', 'mentor_booked')
            ->where('channel', 'whatsapp')
            ->update([
                'body' => "🎯 *Session Confirmed!*\n\nHi {{name}}, your 1:1 session with *{{with}}* is booked for *{{when}}*.\n\n📞 They will connect with you directly at the scheduled time — please be available on this number.\n\nDetails & calendar invite: {{link}}",
                'updated_at' => now(),
            ]);

        DB::table('message_templates')
            ->where('key', 'mentor_booked')
            ->where('channel', 'email')
            ->update([
                'subject' => 'Session confirmed — {{when}}',
                'body' => "Hi {{name}}, great news — your 1:1 session is confirmed!\n"
                    ."With: {{with}}\n"
                    ."When: {{when}}\n"
                    ."Sign in: {{link}}\n"
                    .'They will connect with you directly at the scheduled time — please be available. The calendar invite is on your Mentors page.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')->where('feature', 'mentor')
            ->update(['name' => 'Extra mentor 1:1', 'grant_amount' => 1, 'updated_at' => now()]);
    }
};
