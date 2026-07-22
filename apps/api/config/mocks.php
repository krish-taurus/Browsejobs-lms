<?php

declare(strict_types=1);

/*
| AI mock interviewer core (PRD §6.6). Voice transport + entitlement quotas
| arrive in P4.3; text practice is gated by the monetization flag
| text_practice_enabled (off by default per founder preference).
*/
return [
    'voice' => [
        // 10-minute hard cap per session (PRD §6.6); the assistant is told to
        // wrap up gracefully in the final minute rather than cutting off.
        'max_seconds' => (int) env('MOCK_VOICE_MAX_SECONDS', 600),
        'wrapup_seconds' => 60,
    ],

    // Interviewer questions per session (the opening question counts).
    'max_questions' => (int) env('MOCKS_MAX_QUESTIONS', 6),

    // Minimum candidate answers before a scorecard can be generated.
    'min_answers' => (int) env('MOCKS_MIN_ANSWERS', 2),

    // Default number of mocks a module requires, when the module sets no override
    // (PRD §6.6 "N mocks per module"). Opened when the student completes the module.
    'per_module' => (int) env('MOCKS_PER_MODULE', 3),

    // Best AI-mock overall score that unlocks the human mock (PRD progression
    // gate: AI mock threshold → human mock → placement pool).
    'human_gate_score' => (int) env('MOCKS_HUMAN_GATE_SCORE', 70),
];
