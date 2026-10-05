<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'meta' => [
        // Meta Lead Ads webhook (PRD §6.12). `app_secret` signs the POST body
        // (X-Hub-Signature-256); `verify_token` guards the GET subscription
        // handshake; `tenant_slug` maps the connected Meta business to a tenant.
        'app_secret' => env('META_APP_SECRET', ''),
        'verify_token' => env('META_LEAD_WEBHOOK_VERIFY_TOKEN', ''),
        'tenant_slug' => env('META_TENANT_SLUG', ''),
    ],

    'razorpay' => [
        // Payments (PRD §6.8). Test keys in non-prod; tests mock the client.
        'key_id' => env('RAZORPAY_KEY_ID', ''),
        'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
    ],

    'whatsapp' => [
        // WhatsApp Cloud API (PRD §6.9). `app_secret` signs the inbound webhook
        // (X-Hub-Signature-256); `verify_token` guards the GET handshake. Tests
        // mock the client.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        'app_secret' => env('WHATSAPP_APP_SECRET', ''),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v21.0'),
        // Maps the connected WABA to a tenant slug (single-business P2.4).
        'tenant_slug' => env('WHATSAPP_TENANT_SLUG', ''),
    ],

    'anthropic' => [
        // AI Service Layer (CLAUDE.md / PRD §6.4). Tests fake the client.
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    ],

    'vapi' => [
        // Voice mock transport (PRD §6.6, P4.3). Vapi by default; Retell or any
        // compatible provider slots in behind VoiceMockClient. No key = voice off.
        'api_key' => env('VAPI_API_KEY', ''),
        'base_url' => env('VAPI_BASE_URL', 'https://api.vapi.ai'),
        'webhook_secret' => env('VAPI_WEBHOOK_SECRET', ''),
    ],

    'judge0' => [
        // Coding labs code execution (PRD §6.5). Self-hosted; tests fake the client.
        'url' => env('JUDGE0_URL', 'http://127.0.0.1:2358'),
        'auth_token' => env('JUDGE0_AUTH_TOKEN', ''),
    ],

    'jsearch' => [
        // Licensed job-feed API (PRD §6.22, ADR 0045). Legally aggregates postings
        // from LinkedIn/Indeed/Naukri/Glassdoor via Google-for-Jobs — NOT scraping.
        // No key = the feed's API source stays off (Null transport). RapidAPI host.
        'api_key' => env('JSEARCH_API_KEY', ''),
        'host' => env('JSEARCH_API_HOST', 'jsearch.p.rapidapi.com'),
    ],

    'apify' => [
        // Apify actor runs for scraper job-feed sources (ADR 0048 — explicit
        // founder override of the PRD no-scraping rule). No token = scraper
        // sources stay off (Null transport). Tests fake the transport.
        'token' => env('APIFY_TOKEN', ''),
        'base_url' => env('APIFY_BASE_URL', 'https://api.apify.com'),
    ],

    'zoom' => [
        'account_id' => env('ZOOM_ACCOUNT_ID', ''),
        'client_id' => env('ZOOM_CLIENT_ID', ''),
        'client_secret' => env('ZOOM_CLIENT_SECRET', ''),
        'webhook_secret' => env('ZOOM_WEBHOOK_SECRET_TOKEN', ''),
        'base_url' => env('ZOOM_BASE_URL', 'https://api.zoom.us/v2'),
        'oauth_url' => env('ZOOM_OAUTH_URL', 'https://zoom.us/oauth/token'),
        // Default meeting host (a Zoom user id or email). Server-to-Server OAuth has no
        // "me" user context, so set this to your account owner's email to host meetings
        // without allocating a per-trainer Zoom license. Empty falls back to "me".
        'default_host_id' => env('ZOOM_DEFAULT_HOST_ID', ''),
        // Grace minutes after start before a join counts as "late".
        'late_grace_minutes' => (int) env('ZOOM_LATE_GRACE_MINUTES', 10),
    ],

    // Google Drive review intake (Platform Spec §3). The folder link is editable in
    // Admin → Settings so it can change without a deploy; the service-account JSON is
    // the credential the scheduled sync reads the folder with.
    'google_drive' => [
        'reviews_folder' => env('GOOGLE_DRIVE_REVIEWS_FOLDER', ''),
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON', ''),
    ],

    /*
    | Social + review-gate links (Platform Spec §3). Shown to a 4★+ candidate so
    | they can post on Google and follow us. Tenant-owned via platform settings.
    */
    'social' => [
        'google_review_url' => env('SOCIAL_GOOGLE_REVIEW_URL', ''),
        'instagram_url' => env('SOCIAL_INSTAGRAM_URL', ''),
        'youtube_url' => env('SOCIAL_YOUTUBE_URL', ''),
    ],

    /*
    | Background verification sources (PRD-E F9). Platform-owned, entered in
    | Admin → Settings → BGV; env holds only placeholders so nothing real is
    | ever committed. Empty credentials are not an error — the provider
    | reports that it cannot run and the check falls to a human reviewer.
    */
    'digilocker' => [
        'base_url' => env('DIGILOCKER_BASE_URL', ''),
        'client_id' => env('DIGILOCKER_CLIENT_ID', ''),
        'client_secret' => env('DIGILOCKER_CLIENT_SECRET', ''),
    ],

    'epfo' => [
        'base_url' => env('EPFO_BASE_URL', ''),
        'api_key' => env('EPFO_API_KEY', ''),
    ],

    // Which sandbox runs coding-lab submissions: 'piston' (self-hosted, default)
    // or 'judge0'. Piston runs in Docker on this host — see PistonJudge0Client.
    'code_runner' => env('CODE_RUNNER', 'piston'),

    'piston' => [
        'url' => env('PISTON_URL', 'http://127.0.0.1:2000'),
    ],

    /*
    | The voice the AI interviewer speaks in. Without a key the mock room falls
    | back to the browser's own speech synthesis, so the interview still has a
    | voice — just whichever robot the candidate's device ships with.
    |
    | ELEVENLABS_VOICE_ID is the voice: change it here and every question is
    | read in the new one. Find ids at elevenlabs.io → Voices → ID.
    */
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY', ''),
        'voice_id' => env('ELEVENLABS_VOICE_ID', ''),
        'model' => env('ELEVENLABS_MODEL', 'eleven_turbo_v2_5'),
    ],

    /*
    | The CRM, for the batch-chat bridge. Students hold no CRM account, so
    | this server relays their messages there on their behalf. The secret
    | is shared with the CRM and never reaches a browser.
    */
    'crm' => [
        'url' => env('CRM_URL', 'https://crm.browsejobs.ai'),
        'internal_secret' => env('CRM_INTERNAL_SECRET', ''),
    ],

    /*
    | The Next.js web app, for on-demand ISR revalidation of the public job
    | board — so closing or publishing a JD stops showing the stale version
    | immediately instead of waiting out the timed revalidate window. The
    | secret matches the web app's own REVALIDATE_SECRET and never reaches a
    | browser.
    */
    'web' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'revalidate_secret' => env('WEB_REVALIDATE_SECRET', ''),
    ],

];
