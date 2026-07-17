<?php

declare(strict_types=1);

/*
| Mentor & interview scheduling (PRD §6.11). All availability math runs in
| IST; sessions are stored UTC.
*/
return [
    'timezone' => 'Asia/Kolkata',
    'slot_minutes' => (int) env('MENTORING_SLOT_MINUTES', 30),
    // How far ahead the combined calendar shows slots.
    'window_days' => (int) env('MENTORING_WINDOW_DAYS', 14),
    // The 4-hour notice rule: booking lead time AND the student
    // reschedule/cancel cutoff.
    'cancel_notice_hours' => (int) env('MENTORING_NOTICE_HOURS', 4),
];
