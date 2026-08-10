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

        'apify' => [
            'label' => 'Job scrapers (Apify)',
            'help' => 'One Apify token runs all your scraper sources — LinkedIn, Naukri, or any actor — under your Apify account (Apify Console → Settings → API tokens). A source on the Job feed page can also carry its own token override if you ever bill an actor to a different Apify account.',
            'fields' => [
                ['key' => 'token', 'label' => 'Apify API token', 'type' => 'secret', 'config' => 'services.apify.token'],
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
        /*
        | Background verification (PRD-E F9). BrowseJobs holds these
        | credentials, not the employer: an employer buys the outcome of a
        | check, never the ability to run one against a candidate directly.
        |
        | Each kind can be routed independently, so identity can go to
        | DigiLocker while employment still sits with a human reviewer.
        | Leaving a kind on "manual" is always safe — nothing is fabricated,
        | the check simply waits for the ops queue.
        */
        'bgv' => [
            'label' => 'Background verification (BGV)',
            'help' => 'BrowseJobs runs these checks on candidates — employers only ever see the result. Route each check to a provider and give that provider its credentials. Anything left on "Manual review" goes to your ops queue instead, which is the safe default: no check is ever auto-passed without a source behind it.',
            'fields' => [
                ['key' => 'identity_provider', 'label' => 'Identity — checked by', 'type' => 'select',
                    'options' => ['manual', 'digilocker'], 'config' => 'verification.routing.identity'],
                ['key' => 'education_provider', 'label' => 'Education — checked by', 'type' => 'select',
                    'options' => ['manual', 'digilocker'], 'config' => 'verification.routing.education'],
                ['key' => 'employment_provider', 'label' => 'Employment — checked by', 'type' => 'select',
                    'options' => ['manual', 'epfo'], 'config' => 'verification.routing.employment'],
                ['key' => 'documents_provider', 'label' => 'Documents — checked by', 'type' => 'select',
                    'options' => ['manual', 'ai_documents'], 'config' => 'verification.routing.documents'],

                ['key' => 'digilocker_base_url', 'label' => 'DigiLocker — base URL', 'type' => 'text',
                    'config' => 'services.digilocker.base_url'],
                ['key' => 'digilocker_client_id', 'label' => 'DigiLocker — client ID', 'type' => 'text',
                    'config' => 'services.digilocker.client_id'],
                ['key' => 'digilocker_client_secret', 'label' => 'DigiLocker — client secret', 'type' => 'secret',
                    'config' => 'services.digilocker.client_secret'],

                ['key' => 'epfo_base_url', 'label' => 'EPFO / BGV vendor — base URL', 'type' => 'text',
                    'config' => 'services.epfo.base_url'],
                ['key' => 'epfo_api_key', 'label' => 'EPFO / BGV vendor — API key', 'type' => 'secret',
                    'config' => 'services.epfo.api_key'],
            ],
        ],

        'classes' => [
            'label' => 'Classes & fees',
            'help' => 'How long before a class the Join button opens, and how long a student may keep attending after a fee falls due before live classes lock.',
            'fields' => [
                [
                    'key' => 'join_opens_minutes_before',
                    'label' => 'Join opens (minutes before class)',
                    'type' => 'select',
                    'options' => ['1', '5', '10', '15', '30', '60'],
                    'config' => 'classes.join_opens_minutes_before',
                ],
                [
                    'key' => 'fee_grace_days',
                    'label' => 'Attend without paying (days after due)',
                    'type' => 'select',
                    'options' => ['0', '3', '5', '7', '10', '14', '21', '30'],
                    'config' => 'fees.ladder.grace_days',
                ],
                [
                    'key' => 'fee_hard_block_after_days',
                    'label' => 'Days before a full lockout',
                    'type' => 'select',
                    'options' => ['3', '5', '7', '10', '14', '21', '30'],
                    'config' => 'fees.ladder.hard_block_after_days',
                ],
            ],
        ],

        'funnel' => [
            'label' => 'Enrolment funnel',
            'help' => 'How often each course runs its masterclass. Weekly keeps every course on its fixed weekend day; a longer cycle dates the next masterclass from the last one, so leads collect into a single batch per cycle. Bootcamp stays 7 days either way.',
            'fields' => [
                [
                    'key' => 'masterclass_interval_days',
                    'label' => 'Masterclass frequency (days)',
                    'type' => 'select',
                    'options' => ['7', '14', '20', '25', '30'],
                    'config' => 'funnel.masterclass_interval_days',
                ],
            ],
        ],

        'social' => [
            'label' => 'Reviews & social links',
            'help' => 'Where a 4★+ candidate is sent to post their review and follow you. Paste your Google "write a review" link and your Instagram / YouTube URLs — they appear on the review page after a candidate rates 4 stars or more.',
            'fields' => [
                ['key' => 'google_review_url', 'label' => 'Google review link', 'type' => 'text', 'config' => 'services.social.google_review_url'],
                ['key' => 'instagram_url', 'label' => 'Instagram URL', 'type' => 'text', 'config' => 'services.social.instagram_url'],
                ['key' => 'youtube_url', 'label' => 'YouTube URL', 'type' => 'text', 'config' => 'services.social.youtube_url'],
            ],
        ],
    ],
];
