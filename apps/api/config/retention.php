<?php

declare(strict_types=1);

/*
| Review protection & retention (PRD §6.20). NPS routing is UNCONDITIONED —
| promoters are invited to review, never rewarded for it (policy-safe).
*/
return [
    'nps' => [
        // Days after enrolment for the week-1 pulse window.
        'week1_after_days' => 7,
        // Course topic-completion percentages that trigger the later pulses.
        'mid_progress_pct' => 50,
        'pre_placement_progress_pct' => 80,
        'promoter_min' => 9,
        'detractor_max' => 6,
        // Public Google review target (founder input; empty = no routing).
        'google_review_url' => env('GOOGLE_REVIEW_URL', ''),
    ],

    'rage' => [
        'failed_payments' => 2,       // within window_days
        'low_csat_tickets' => 2,      // csat <= 2 within 90 days
        'window_days' => 30,
        // Engagement cliff: active in the prior window, silent this many days.
        'silent_days' => 7,
        // Don't re-alert the same student within this many days.
        'cooldown_days' => 14,
    ],

    'pause' => [
        'max_days' => (int) env('RETENTION_PAUSE_MAX_DAYS', 90),
    ],
];
