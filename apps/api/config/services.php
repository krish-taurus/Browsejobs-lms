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

    'judge0' => [
        // Coding labs code execution (PRD §6.5). Self-hosted; tests fake the client.
        'url' => env('JUDGE0_URL', 'http://127.0.0.1:2358'),
        'auth_token' => env('JUDGE0_AUTH_TOKEN', ''),
    ],

    'zoom' => [
        'account_id' => env('ZOOM_ACCOUNT_ID', ''),
        'client_id' => env('ZOOM_CLIENT_ID', ''),
        'client_secret' => env('ZOOM_CLIENT_SECRET', ''),
        'webhook_secret' => env('ZOOM_WEBHOOK_SECRET_TOKEN', ''),
        'base_url' => env('ZOOM_BASE_URL', 'https://api.zoom.us/v2'),
        'oauth_url' => env('ZOOM_OAUTH_URL', 'https://zoom.us/oauth/token'),
        // Grace minutes after start before a join counts as "late".
        'late_grace_minutes' => (int) env('ZOOM_LATE_GRACE_MINUTES', 10),
    ],

];
