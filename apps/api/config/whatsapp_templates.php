<?php

declare(strict_types=1);

/**
 * Maps a local `message_templates.key` onto the Meta-approved template that must
 * carry it. Business-initiated WhatsApp only delivers through an approved
 * template — a free-form text is accepted by Meta (it even returns a wamid) and
 * then silently dropped outside the 24-hour customer service window.
 *
 * params: the `$vars` keys, in the order of the template's {{1}}, {{2}}, … .
 * auth:   AUTHENTICATION templates repeat the code in their copy-code button,
 *         so the payload needs a button component as well as the body.
 */
return [
    // Seated in a cohort: "your seat is confirmed, sign in with this WhatsApp
    // number". bj_batch_joined takes name, batch and the start label — it has no
    // link parameter, its body points at the student portal itself.
    'batch_welcome' => [
        'name' => env('WHATSAPP_BATCH_WELCOME_TEMPLATE', 'bj_batch_joined'),
        'params' => ['name', 'batch', 'starts'],
    ],

    'otp' => [
        'name' => env('WHATSAPP_OTP_TEMPLATE', 'bj_otp'),
        'params' => ['code'],
        'auth' => true,
    ],
];
