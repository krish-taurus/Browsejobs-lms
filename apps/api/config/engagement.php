<?php

declare(strict_types=1);

/*
| P3.7 engagement fan-out limits (PRD §6.18/§6.19). In-app is the default
| channel; WhatsApp/email for these features is weekly, marketing-category,
| and opt-in only (Messenger enforces).
*/
return [
    'celebration_fanout' => (int) env('ENGAGEMENT_CELEBRATION_FANOUT', 200),
    'content_fanout' => (int) env('ENGAGEMENT_CONTENT_FANOUT', 200),
    // Curated items younger than this feed the daily digest.
    'pulse_window_days' => (int) env('ENGAGEMENT_PULSE_WINDOW_DAYS', 3),
];
