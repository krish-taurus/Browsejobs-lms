<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Day-5 bootcamp payment nudge (funnel automation): after a student has
     * attended 5 bootcamp days, they get this message on WhatsApp + email
     * inviting them to secure their paid-batch seat. Seeded here (not only in
     * MessagingSeeder) so existing installs get the template without reseeding.
     */
    public function up(): void
    {
        $templates = [
            ['channel' => 'whatsapp', 'subject' => null,
                'body' => 'Hi {{name}}, amazing — you have completed {{days}} days of your free bootcamp! Your seat in the {{course}} paid batch is waiting. Secure it now: {{link}}'],
            ['channel' => 'email', 'subject' => 'Your {{course}} seat is waiting',
                'body' => 'Hi {{name}}, you have completed {{days}} days of your free bootcamp — great commitment! The next step is the full {{course}} program. Secure your seat here: {{link}}. Your counselor can help with payment plans.'],
        ];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($templates as $template) {
                $exists = DB::table('message_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'bootcamp_payment_nudge')
                    ->where('channel', $template['channel'])
                    ->exists();

                if (! $exists) {
                    DB::table('message_templates')->insert([
                        'tenant_id' => $tenantId,
                        'key' => 'bootcamp_payment_nudge',
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
        DB::table('message_templates')->where('key', 'bootcamp_payment_nudge')->delete();
    }
};
