<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Line-based body for the credentials email so the branded mail template
     * renders Login/Password as a detail card and "Sign in:" as the button
     * (resources/views/emails/message.blade.php parses these line shapes).
     */
    public function up(): void
    {
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'email')
            ->update([
                'subject' => 'Your batch {{batch}} login details',
                'body' => "Hi {{name}}, congratulations on completing your masterclass! 🎉\n"
                    ."You are now enrolled and ready to start learning.\n"
                    ."Batch: {{batch}}\n"
                    ."Starts: {{starts}}\n"
                    ."Login: {{login}}\n"
                    ."Password: {{password}}\n"
                    ."Sign in: {{link}}\n"
                    .'Please change your password after your first login. See you in class!',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('key', 'batch_credentials')
            ->where('channel', 'email')
            ->update([
                'body' => 'Hi {{name}}, congratulations on completing your masterclass! You are now enrolled in batch {{batch}}, starting {{starts}}. Sign in at {{link}} with Login: {{login}} and Password: {{password}}. Please change your password after your first login. See you in class!',
                'updated_at' => now(),
            ]);
    }
};
