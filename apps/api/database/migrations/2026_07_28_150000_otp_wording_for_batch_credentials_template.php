<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Passwordless onboarding wording: the batch message tells the student to
     * sign in with their registered number/email via a one-time code — no
     * password is generated or sent anywhere.
     */
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'whatsapp')
            ->update([
                'body' => 'Hi {{name}}, congratulations! Your seat in batch {{batch}} is confirmed, starting {{starts}}. Open {{link}} and sign in with this WhatsApp number — a one-time code will verify you instantly. Your classes are waiting. See you in class!',
                'updated_at' => now(),
            ]);

        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'email')
            ->update([
                'subject' => 'You are in! Batch {{batch}} — how to sign in',
                'body' => "Hi {{name}}, congratulations! Your seat is confirmed and your classes are ready.\n"
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
        // Previous (password-era) bodies — restored only on rollback.
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'whatsapp')
            ->update([
                'body' => 'Hi {{name}}, congratulations on completing your masterclass! You are now enrolled in batch {{batch}} (starts {{starts}}). Sign in at {{link}} — Login: {{login}} · Password: {{password}}. Please change your password after your first login.',
                'updated_at' => now(),
            ]);
    }
};
