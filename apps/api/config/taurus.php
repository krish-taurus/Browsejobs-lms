<?php

declare(strict_types=1);

/*
| Taurus AI — the live agent command centre (ADR 0052).
|
| Every business gets its own Taurus workspace: its own bots (reporting on
| two floors, ops and recruitment, with a per-workspace bearer token), its own
| members, and its own LLM / ElevenLabs keys. The `brain` settings below are
| the PLATFORM brain — the founder's HQ fallback and the employer hiring floor
| — stored in platform_settings (group `taurus` + the shared `ai` keys).
*/
return [
    'brain' => [
        // Which LLM answers "Ask Taurus". `platform` = whatever the platform's
        // active AI provider is (Settings → AI / LLM); any provider id from
        // config/ai.php forces that one, when it has a key.
        'provider' => env('TAURUS_BRAIN_PROVIDER', 'platform'),
        // Optional model override for the brain; null = the provider's model.
        'model' => env('TAURUS_BRAIN_MODEL'),
    ],

    // The console is owner-only (super-admin role). When this lists emails
    // (comma-separated), the super-admin must also be one of them.
    'owner_emails' => env('TAURUS_OWNER_EMAILS', ''),

    // An agent that has not reported for this long shows as offline.
    'offline_after_minutes' => (int) env('TAURUS_OFFLINE_AFTER_MINUTES', 15),

    // "Today" on the console (done today, spend today) is this calendar day.
    'timezone' => env('TAURUS_TIMEZONE', 'Asia/Kolkata'),

    // How long a workspace invite link stays valid.
    'invite_ttl_days' => (int) env('TAURUS_INVITE_TTL_DAYS', 7),
];
