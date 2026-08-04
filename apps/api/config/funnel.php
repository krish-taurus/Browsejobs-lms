<?php

declare(strict_types=1);

/*
 * Automated enrolment-funnel scheduling defaults. Every class the funnel
 * creates can still be moved or cancelled afterwards (CRM Masterclasses page /
 * admin panel) — these only decide where classes land by default.
 */
return [
    // Master switch for the AUTOMATED funnel (weekend masterclass batches,
    // masterclass→bootcamp rollover, bootcamp→paid conversion). OFF means the
    // team creates batches and allocates students manually from the CRM; the
    // day-5 payment nudge keeps running either way.
    'auto_advance' => (bool) env('FUNNEL_AUTO_ADVANCE', false),

    // The daily "simulated live" masterclass showing: the recorded masterclass
    // plays as-if-live at this time every day, so a Monday lead never waits
    // for Saturday. The live weekend masterclass is unaffected.
    'masterclass_watch_time' => env('FUNNEL_MASTERCLASS_WATCH_TIME', '20:00'),

    // Masterclass slot on its weekend day.
    'masterclass_time' => env('FUNNEL_MASTERCLASS_TIME', '11:00'),
    'masterclass_duration_minutes' => (int) env('FUNNEL_MASTERCLASS_DURATION', 90),

    // How often a course runs its masterclass. 7 keeps the original weekly
    // cadence (every course on its fixed weekend day). A longer interval dates
    // the next masterclass from the last one and still lands it on that
    // course's weekend day, so leads accumulate into one batch per cycle.
    // Changeable from the CRM without a deploy — see config/platform_settings.php.
    'masterclass_interval_days' => (int) env('FUNNEL_MASTERCLASS_INTERVAL_DAYS', 7),

    // Bootcamp: one class every day for its 7 days.
    'class_time' => env('FUNNEL_CLASS_TIME', '19:00'),
    'class_duration_minutes' => (int) env('FUNNEL_CLASS_DURATION', 90),

    // Paid batch: weekday syllabus series, one topic per class.
    'paid_class_weekdays' => [1, 2, 3, 4, 5],
    'paid_class_fallback_count' => (int) env('FUNNEL_PAID_CLASS_FALLBACK_COUNT', 20),

    // After attending this many bootcamp days, the student gets the
    // WhatsApp + email nudge to secure their paid-batch seat.
    'payment_nudge_after_days' => (int) env('FUNNEL_PAYMENT_NUDGE_DAYS', 5),
];
