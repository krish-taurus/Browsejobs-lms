<?php

declare(strict_types=1);

return [
    /*
    | Messaging hub (PRD §6.9). WhatsApp is the primary channel; email + in-app
    | dashboard notifications follow. Utility (transactional) messages bypass the
    | quiet-hours / frequency-cap / opt-out guards; marketing respects them.
    */
    'default_channel' => env('MESSAGING_DEFAULT_CHANNEL', 'whatsapp'),

    // Quiet hours in IST — non-urgent sends are deferred to the next open window.
    'quiet_hours' => [
        'timezone' => 'Asia/Kolkata',
        'start' => 21, // 9pm
        'end' => 9,    // 9am
    ],

    // Max non-urgent messages per recipient per rolling 24h.
    'frequency_cap' => (int) env('MESSAGING_FREQUENCY_CAP', 6),

    /*
    | Banned-phrase linter (PRD §6.9 + §14.3 never-claims). Any rendered body or
    | saved template containing one of these is blocked — protects the WABA and
    | keeps radical-honesty compliance. Case-insensitive substring match.
    */
    'banned_phrases' => [
        'guaranteed job',
        'guaranteed placement',
        'job guarantee',
        '100% placement',
        'assured placement',
        'assured job',
        'salary guarantee',
        'guaranteed salary',
        'guaranteed income',
    ],
];
