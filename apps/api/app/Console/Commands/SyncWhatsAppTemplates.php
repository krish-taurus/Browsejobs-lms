<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageTemplate;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Auto-activates Meta-approved WhatsApp templates: pulls the template list
 * from the WABA and, for every key mapped in config/whatsapp_templates.php,
 * writes the template name onto message_templates.name the moment Meta
 * approves it (and clears it again if Meta pauses/disables it). While the
 * name is empty, WhatsApp sends stay free-form session texts (24h window);
 * once set, they go out as approved templates — deliverable to anyone.
 */
final class SyncWhatsAppTemplates extends Command
{
    protected $signature = 'whatsapp:sync-templates';

    protected $description = 'Activate Meta-approved WhatsApp templates onto message_templates.name';

    public function handle(): int
    {
        $waba = (string) config('services.whatsapp.business_account_id');
        $token = (string) config('services.whatsapp.access_token');

        if ($waba === '' || $token === '') {
            $this->warn('WhatsApp business account / token not configured — nothing to sync.');

            return self::SUCCESS;
        }

        $response = Http::withToken($token)
            ->get(rtrim((string) config('services.whatsapp.base_url'), '/')."/{$waba}/message_templates", [
                'fields' => 'name,status,language',
                'limit' => 100,
            ]);

        if ($response->failed()) {
            $this->error('Meta API error: '.mb_substr((string) $response->body(), 0, 200));

            return self::FAILURE;
        }

        $approved = collect($response->json('data', []))
            ->where('status', 'APPROVED')
            ->pluck('name')
            ->all();

        $activated = 0;
        $deactivated = 0;

        foreach ((array) config('whatsapp_templates', []) as $key => $map) {
            $waName = $map['name'] ?? null;
            if ($waName === null) {
                continue;
            }

            $rows = MessageTemplate::query()->withoutGlobalScope(TenantScope::class)
                ->where('key', $key)
                ->where('channel', 'whatsapp')
                ->get();

            foreach ($rows as $row) {
                if (in_array($waName, $approved, true) && $row->name !== $waName) {
                    $row->update(['name' => $waName]);
                    $activated++;
                } elseif (! in_array($waName, $approved, true) && $row->name === $waName) {
                    $row->update(['name' => null]);
                    $deactivated++;
                }
            }
        }

        $this->info(sprintf(
            'Approved on Meta: %s · activated %d, deactivated %d template link(s).',
            $approved === [] ? '(none yet)' : implode(', ', $approved),
            $activated,
            $deactivated,
        ));

        return self::SUCCESS;
    }
}
