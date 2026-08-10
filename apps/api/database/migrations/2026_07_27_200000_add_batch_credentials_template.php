<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Post-masterclass credentials message (funnel automation): when a
     * masterclass completes and its attendees roll into the bootcamp batch,
     * each newly created student receives their batch number + login
     * credentials (identifier + generated password) on WhatsApp + email.
     * Seeded here so existing installs get the template without reseeding.
     */
    public function up(): void
    {
        $templates = [
            ['channel' => 'whatsapp', 'subject' => null,
                'body' => 'Hi {{name}}, congratulations on completing your masterclass! You are now enrolled in batch {{batch}} (starts {{starts}}). Sign in at {{link}} — Login: {{login}} · Password: {{password}}. Please change your password after your first login.'],
            ['channel' => 'email', 'subject' => 'Your batch {{batch}} login details',
                'body' => 'Hi {{name}}, congratulations on completing your masterclass! You are now enrolled in batch {{batch}}, starting {{starts}}. Sign in at {{link}} with Login: {{login}} and Password: {{password}}. Please change your password after your first login. See you in class!'],
        ];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($templates as $template) {
                $exists = DB::table('message_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'batch_credentials')
                    ->where('channel', $template['channel'])
                    ->exists();

                if (! $exists) {
                    DB::table('message_templates')->insert([
                        'tenant_id' => $tenantId,
                        'key' => 'batch_credentials',
                        'channel' => $template['channel'],
                        'category' => 'utility',
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'batch_credentials')->delete();
    }
};
