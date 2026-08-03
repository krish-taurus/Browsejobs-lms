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
    'otp' => [
        'name' => env('WHATSAPP_OTP_TEMPLATE', 'bj_otp'),
        'params' => ['code'],
        'auth' => true,
    ],
];
