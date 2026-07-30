<?php

declare(strict_types=1);

/**
 * Employer module configuration (PRD-E). Server-owned limits — never
 * client-sent. Credit defaults feed F8; invite TTL feeds F1.
 */
return [
    // Days an employer invite token stays valid. Single-use regardless.
    'invite_ttl_days' => (int) env('EMPLOYER_INVITE_TTL_DAYS', 7),

    // Free-tier monthly credit defaults (PRD-E §F8, founder-confirmable).
    'credits' => [
        'sourcing_per_month' => (int) env('EMPLOYER_SOURCING_CREDITS', 100),
        'interviews_per_month' => (int) env('EMPLOYER_INTERVIEW_CREDITS', 350),
    ],
];
