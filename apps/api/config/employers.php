<?php

declare(strict_types=1);

/**
 * Employer module configuration (PRD-E). Server-owned limits — never
 * client-sent. Credit defaults feed F8; invite TTL feeds F1.
 */
return [
    // Days an employer invite token stays valid. Single-use regardless.
    'invite_ttl_days' => (int) env('EMPLOYER_INVITE_TTL_DAYS', 7),

    // Hours a candidate has to complete an invited L1/L2 round (same-day
    // chaining works because rounds are async and unlock instantly).
    'interview_window_hours' => (int) env('EMPLOYER_INTERVIEW_WINDOW_HOURS', 48),

    // Free-tier monthly credit defaults (PRD-E §F8, founder-confirmable).
    'credits' => [
        'sourcing_per_month' => (int) env('EMPLOYER_SOURCING_CREDITS', 100),
        'interviews_per_month' => (int) env('EMPLOYER_INTERVIEW_CREDITS', 350),
    ],
];
