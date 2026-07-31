<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Instant "new class on your calendar" announcement: sent to every student
     * of a batch the moment an admin manually schedules a class for it (the
     * reminder ladder handles the run-up to start time). Seeded here so
     * existing installs get the template without reseeding.
     */
    public function up(): void
    {
        $templates = [
            ['channel' => 'whatsapp', 'subject' => null,
                'body' => 'Hi {{name}}, a new class "{{title}}" has been scheduled for your batch {{batch}} on {{when}}. Join from your dashboard: {{link}}'],
            ['channel' => 'email', 'subject' => 'New class scheduled: {{title}}',
                'body' => 'Hi {{name}}, a new class "{{title}}" has been scheduled for your batch {{batch}} on {{when}}. You can join it from your dashboard: {{link}}. If you miss it, the recording will be available there afterwards.'],
        ];

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($templates as $template) {
                $exists = DB::table('message_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'class_scheduled')
                    ->where('channel', $template['channel'])
                    ->exists();

                if (! $exists) {
                    DB::table('message_templates')->insert([
                        'tenant_id' => $tenantId,
                        'key' => 'class_scheduled',
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
        DB::table('message_templates')->where('key', 'class_scheduled')->delete();
    }
};
