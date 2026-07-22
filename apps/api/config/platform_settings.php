<?php

declare(strict_types=1);

/*
| Platform integration settings editable in the admin panel (super-admin only).
|
| This whitelist is the single source of truth: it drives the admin form, the
| validation, the masking of secrets, AND the runtime config override
| (App\Support\Settings\PlatformSettings). A setting can only be stored and applied if
| it appears here — arbitrary config paths can never be written from the UI.
|
| `config` is the dot-path a stored value overrides at boot. Empty stored values are
| ignored, so anything left blank falls back to its .env default. Every value is stored
| encrypted at rest; `type: secret` fields are additionally never returned to the browser.
*/

/** Build the three fields (key, base URL, model) an LLM provider exposes. */
$provider = static fn (string $id, string $label): array => [
    ['key' => "{$id}_api_key", 'label' => "{$label} — API key", 'type' => 'secret', 'config' => "ai.providers.{$id}.api_key"],
    ['key' => "{$id}_base_url", 'label' => "{$label} — base URL", 'type' => 'text', 'config' => "ai.providers.{$id}.base_url"],
    ['key' => "{$id}_model", 'label' => "{$label} — model", 'type' => 'text', 'config' => "ai.providers.{$id}.model"],
];

return [
    'groups' => [
        'ai' => [
            'label' => 'AI / LLM',
            'help' => 'Keys for all providers can be stored so you can switch instantly. Leave the active provider on "auto" to use whichever provider has a key — or pick one to force it.',
            'fields' => array_merge(
                [[
                    'key' => 'provider',
                    'label' => 'Active provider',
                    'type' => 'select',
                    'options' => ['auto', 'anthropic', 'openai', 'kimi', 'deepseek', 'grok', 'custom'],
                    'config' => 'ai.provider',
                ]],
                $provider('anthropic', 'Anthropic'),
                $provider('openai', 'OpenAI'),
                $provider('kimi', 'Kimi / Moonshot'),
                $provider('deepseek', 'DeepSeek'),
                $provider('grok', 'Grok / xAI'),
                $provider('custom', 'Custom (OpenAI-compatible)'),
            ),
        ],

        'whatsapp' => [
            'label' => 'WhatsApp',
            'help' => 'WhatsApp Cloud API credentials for the messaging hub.',
            'fields' => [
                ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text', 'config' => 'services.whatsapp.phone_number_id'],
                ['key' => 'business_account_id', 'label' => 'Business account ID', 'type' => 'text', 'config' => 'services.whatsapp.business_account_id'],
                ['key' => 'access_token', 'label' => 'Access token', 'type' => 'secret', 'config' => 'services.whatsapp.access_token'],
                ['key' => 'verify_token', 'label' => 'Webhook verify token', 'type' => 'secret', 'config' => 'services.whatsapp.verify_token'],
                ['key' => 'app_secret', 'label' => 'App secret', 'type' => 'secret', 'config' => 'services.whatsapp.app_secret'],
                ['key' => 'tenant_slug', 'label' => 'Mapped tenant slug', 'type' => 'text', 'config' => 'services.whatsapp.tenant_slug'],
            ],
        ],

        'vapi' => [
            'label' => 'Voice AI (Vapi)',
            'help' => 'Voice-mock transport. Retell or any compatible provider can slot in behind the same interface; a key here turns voice on.',
            'fields' => [
                ['key' => 'api_key', 'label' => 'Vapi API key', 'type' => 'secret', 'config' => 'services.vapi.api_key'],
                ['key' => 'base_url', 'label' => 'Vapi base URL', 'type' => 'text', 'config' => 'services.vapi.base_url'],
                ['key' => 'webhook_secret', 'label' => 'Vapi webhook secret', 'type' => 'secret', 'config' => 'services.vapi.webhook_secret'],
            ],
        ],

        'zoom' => [
            'label' => 'Zoom',
            'help' => 'Server-to-Server OAuth app credentials for live-class meetings.',
            'fields' => [
                ['key' => 'account_id', 'label' => 'Account ID', 'type' => 'text', 'config' => 'services.zoom.account_id'],
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'config' => 'services.zoom.client_id'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'secret', 'config' => 'services.zoom.client_secret'],
                ['key' => 'webhook_secret', 'label' => 'Webhook secret token', 'type' => 'secret', 'config' => 'services.zoom.webhook_secret'],
            ],
        ],

        'google_drive' => [
            'label' => 'Google Drive reviews',
            'help' => 'Paste the shared Drive folder link your team drops reviews into — you can change it here anytime. The service-account JSON lets the scheduled sync read that folder (share the folder to the service account as Viewer).',
            'fields' => [
                ['key' => 'reviews_folder', 'label' => 'Reviews folder link', 'type' => 'text', 'config' => 'services.google_drive.reviews_folder'],
                ['key' => 'service_account_json', 'label' => 'Service account JSON', 'type' => 'secret', 'config' => 'services.google_drive.service_account_json'],
            ],
        ],
    ],
];
