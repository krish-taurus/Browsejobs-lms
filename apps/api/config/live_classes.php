<?php

declare(strict_types=1);

return [
    /*
    | Reminder ladder — minutes before a live session, each paired with a human
    | label used in the message.
    |
    | One rung only, by decision: students get a single reminder 5 minutes before
    | class, in the BrowseJobs house format. The old 12h/2h/1h rungs were removed
    | because they went out through the generic approved template ("...starts 2h.
    | Join here: <magic link>"), which is not the agreed wording.
    |
    | Adding a rung here is all it takes to bring one back — but a new rung has no
    | approved WhatsApp template of its own, so it would fall back to the generic
    | one again unless a template is registered for it.
    */
    'reminder_offsets' => [
        ['minutes' => 5, 'label' => '5min'],
    ],

    // Minutes before a session that the trainer's AI pre-class brief fires (PRD §6.10).
    // Rides the same reminder rail + token as the student reminders.
    'brief_offset_minutes' => 30,

    // "Join Class" link on the 5-minute reminder. A plain, readable address the
    // student can recognise, instead of a long signed magic URL. The trade-off
    // is deliberate: they sign in themselves rather than being logged in by the
    // link. Set to null to go back to the one-tap magic link.
    'join_url' => env('LIVE_CLASS_JOIN_URL', 'https://browsejobs.ai/classes'),
];
