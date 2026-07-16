<?php

declare(strict_types=1);

/*
| AI Service Layer (CLAUDE.md / PRD §6.4). The single app/Services/AI gateway
| reads this. Costs are integer micro-rupees (₹1 = 1_000_000 micros) for
| token-level precision; the daily budget is a token cap per student.
*/
return [
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    // Per-student daily token budget, enforced in the gateway (CLAUDE.md).
    'daily_token_budget' => (int) env('AI_DAILY_TOKEN_BUDGET_PER_STUDENT', 200_000),

    // Micro-rupees per million tokens (placeholder pricing; tune per contract).
    'pricing' => [
        'input_micros_per_mtok' => (int) env('AI_INPUT_MICROS_PER_MTOK', 250_000_000),
        'output_micros_per_mtok' => (int) env('AI_OUTPUT_MICROS_PER_MTOK', 1_250_000_000),
    ],
];
