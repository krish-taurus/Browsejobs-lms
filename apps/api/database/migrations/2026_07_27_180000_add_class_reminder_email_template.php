<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Class reminders originally existed only as a WhatsApp template, so the
     * 12h/2h/5min ladder never reached students by email. This adds the email
     * variant; MessengerSessionNotifier now sends reminders on both channels.
     */
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $exists = DB::table('message_templates')
                ->where('tenant_id', $tenantId)
                ->where('key', 'class_reminder')
                ->where('channel', 'email')
                ->exists();

            if (! $exists) {
                DB::table('message_templates')->insert([
                    'tenant_id' => $tenantId,
                    'key' => 'class_reminder',
                    'channel' => 'email',
                    'category' => 'utility',
                    'subject' => 'Your class "{{title}}" starts in {{window}}',
                    'body' => 'Hi {{name}}, your class "{{title}}" starts in {{window}}. Join here: {{link}}',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'class_reminder')->where('channel', 'email')->delete();
    }
};
