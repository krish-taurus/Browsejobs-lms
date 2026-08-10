<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Free-form batch announcement (CRM "Message this batch" form): admins
     * type a message once and every occupying student of the batch receives it
     * on WhatsApp + email. Seeded here so existing installs get the template
     * without reseeding.
     */
    public function up(): void
    {
        $templates = [
            ['channel' => 'whatsapp', 'subject' => null,
                'body' => 'Hi {{name}}, update for your batch {{batch}}: {{message}}'],
            ['channel' => 'email', 'subject' => '{{subject}} — batch {{batch}}',
                'body' => 'Hi {{name}}, an update for your batch {{batch}}: {{message}}'],
        ];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($templates as $template) {
                $exists = DB::table('message_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'batch_announcement')
                    ->where('channel', $template['channel'])
                    ->exists();

                if (! $exists) {
                    DB::table('message_templates')->insert([
                        'tenant_id' => $tenantId,
                        'key' => 'batch_announcement',
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
        DB::table('message_templates')->where('key', 'batch_announcement')->delete();
    }
};
