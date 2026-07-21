<?php

declare(strict_types=1);

/*
| AI Service Layer (CLAUDE.md / PRD §6.4). The single app/Services/AI gateway
| reads this. Costs are integer micro-rupees (₹1 = 1_000_000 micros) for
| token-level precision; the daily budget is a token cap per student.
*/
return [
    /*
    | Active LLM provider. Every entry in `providers` below is selectable via
    | AI_PROVIDER; anything speaking the OpenAI chat-completions dialect
    | (OpenAI, Kimi/Moonshot, DeepSeek, Grok/xAI, Ollama, …) uses the
    | `openai_compatible` driver — adding a vendor is config, not code.
    | Model defaults are placeholders: override per provider in .env.
    */
    'provider' => env('AI_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        ],
        'openai' => [
            'driver' => 'openai_compatible',
            'api_key' => env('OPENAI_API_KEY', ''),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'kimi' => [
            'driver' => 'openai_compatible',
            'api_key' => env('KIMI_API_KEY', ''),
            'base_url' => env('KIMI_BASE_URL', 'https://api.moonshot.ai/v1'),
            'model' => env('KIMI_MODEL', 'kimi-k2-turbo-preview'),
        ],
        'deepseek' => [
            'driver' => 'openai_compatible',
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        ],
        'grok' => [
            'driver' => 'openai_compatible',
            'api_key' => env('GROK_API_KEY', ''),
            'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
            'model' => env('GROK_MODEL', 'grok-4'),
        ],
        // Any other OpenAI-compatible endpoint (Ollama, Together, vLLM, …).
        'custom' => [
            'driver' => 'openai_compatible',
            'api_key' => env('CUSTOM_LLM_API_KEY', ''),
            'base_url' => env('CUSTOM_LLM_BASE_URL', ''),
            'model' => env('CUSTOM_LLM_MODEL', ''),
        ],
    ],

    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    // Seconds to wait for a provider response. Generation calls (syllabus, reports)
    // can take well over the 30s HTTP default on slower models, so allow more.
    // Keep this below the queue job timeout (see the AI jobs' $timeout) so the
    // HTTP layer, not the worker, is what bounds a slow call.
    'http_timeout' => (int) env('AI_HTTP_TIMEOUT', 150),

    // Per-student daily token budget, enforced in the gateway (CLAUDE.md).
    'daily_token_budget' => (int) env('AI_DAILY_TOKEN_BUDGET_PER_STUDENT', 200_000),

    // Micro-rupees per million tokens (placeholder pricing; tune per contract).
    'pricing' => [
        'input_micros_per_mtok' => (int) env('AI_INPUT_MICROS_PER_MTOK', 250_000_000),
        'output_micros_per_mtok' => (int) env('AI_OUTPUT_MICROS_PER_MTOK', 1_250_000_000),
    ],

    // AI Tutor (PRD §6.4). Lexical retrieval + escalation tuning.
    'tutor' => [
        'prompt_version' => (int) env('AI_TUTOR_PROMPT_VERSION', 2),
        'retrieve_k' => (int) env('AI_TUTOR_RETRIEVE_K', 5),
        'repeat_threshold' => (int) env('AI_TUTOR_REPEAT_THRESHOLD', 3),
        'repeat_window_hours' => (int) env('AI_TUTOR_REPEAT_WINDOW_HOURS', 24),
        'max_answer_tokens' => (int) env('AI_TUTOR_MAX_ANSWER_TOKENS', 700),
    ],
];
