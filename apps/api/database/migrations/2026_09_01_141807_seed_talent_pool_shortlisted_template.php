<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the internal message_templates row for talent_pool_shortlisted
 * (WhatsApp). `name` stays null until Meta approves the actual template
 * (see whatsapp:create-talent-templates + whatsapp:sync-templates) — the
 * body here is the fallback session text and the source Messenger renders
 * {{vars}} into for the logged Message row either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('message_templates')->updateOrInsert(
                ['tenant_id' => $tenantId, 'key' => 'talent_pool_shortlisted', 'channel' => 'whatsapp'],
                [
                    'category' => 'utility',
                    'name' => null,
                    'subject' => null,
                    'body' => "Hi {{name}}, good news — your CV has been shortlisted for the {{role}} role at {{company}} on BrowseJobs. Please be ready: an interview call can happen any time. Keep an eye on your phone and email.",
                    'locale' => 'en',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'talent_pool_shortlisted')->delete();
    }
};
