<?php

declare(strict_types=1);

return [
    /*
    | Default reminder ladder — minutes before a live session, each paired with
    | a human label used in the message. Tenants may override via their settings
    | (PRD §6.3: timings configurable per tenant).
    */
    'reminder_offsets' => [
        ['minutes' => 720, 'label' => '12h'],
        ['minutes' => 120, 'label' => '2h'],
        ['minutes' => 60, 'label' => '1h'],
        ['minutes' => 5, 'label' => '5min'],
    ],

    // Minutes before a session that the trainer's AI pre-class brief fires (PRD §6.10).
    // Rides the same reminder rail + token as the student reminders.
    'brief_offset_minutes' => 30,

    // Students can join from this many minutes before the scheduled start —
    // earlier attempts are told when the door opens. After the class ends they
    // are pointed to the recording instead.
    'join_open_minutes' => (int) env('LIVE_CLASS_JOIN_OPEN_MINUTES', 10),
];
