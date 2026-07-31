<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Course-dynamic onboarding wording: "Welcome to {{course}}!" so every
     * student sees their own course name (Data Engineering, Python, …) in the
     * batch message on both channels.
     */
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'whatsapp')
            ->update([
                'body' => "🎓 *Welcome to {{course}}!*\n\nHi {{name}}, congratulations! Your seat in batch *{{batch}}* is confirmed — starting *{{starts}}*.\n\n📲 Sign in with this WhatsApp number (one-time code, no password):\n{{link}}\n\nYour classes and full schedule are waiting inside. See you in class! 🚀",
                'updated_at' => now(),
            ]);

        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'email')
            ->update([
                'subject' => 'Welcome to {{course}} — batch {{batch}}',
                'body' => "Hi {{name}}, congratulations! Your seat is confirmed and your classes are ready.\n"
                    ."Course: {{course}}\n"
                    ."Batch: {{batch}}\n"
                    ."Starts: {{starts}}\n"
                    ."Login: {{login}}\n"
                    ."Sign in: {{link}}\n"
                    .'No password needed — enter your number or email on the sign-in page and we will send you a one-time code (OTP). See you in class!',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'whatsapp')
            ->update([
                'body' => 'Hi {{name}}, congratulations! Your seat in batch {{batch}} is confirmed, starting {{starts}}. Open {{link}} and sign in with this WhatsApp number — a one-time code will verify you instantly. Your classes are waiting. See you in class!',
                'updated_at' => now(),
            ]);
    }
};
