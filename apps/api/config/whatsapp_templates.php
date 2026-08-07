<?php

declare(strict_types=1);

/**
 * Maps a local `message_templates.key` onto the Meta-approved template that must
 * carry it. Business-initiated WhatsApp only delivers through an approved
 * template — a free-form text is accepted by Meta (it even returns a wamid) and
 * then silently dropped outside the 24-hour customer service window.
 *
 * A key missing from this map is therefore a message students never receive,
 * with nothing in the logs to say so. `whatsapp:sync-templates` reads this file
 * hourly and activates each mapping once Meta approves the template.
 *
 * params: the `$vars` keys, in the order of the template's {{1}}, {{2}}, … .
 *         The count must match the template exactly or the send fails.
 * auth:   AUTHENTICATION templates repeat the code in their copy-code button,
 *         so the payload needs a button component as well as the body.
 */
return [
    // Seated in a cohort: "your seat is confirmed, sign in with this WhatsApp
    // number". v2 replaced the original because that one had the wrong portal
    // domain (browsejobs.in) baked into its body, which 404s.
    'batch_welcome' => [
        'name' => env('WHATSAPP_BATCH_WELCOME_TEMPLATE', 'bj_batch_joined_v2'),
        'params' => ['name', 'batch', 'starts'],
    ],

    // Login details. bj_batch_confirmed names the email we sent them to rather
    // than carrying a URL, so it cannot go stale the way v1 did.
    'batch_credentials' => [
        'name' => env('WHATSAPP_BATCH_CREDENTIALS_TEMPLATE', 'bj_batch_confirmed'),
        'params' => ['name', 'batch_course', 'starts', 'login'],
    ],

    'otp' => [
        'name' => env('WHATSAPP_OTP_TEMPLATE', 'bj_otp'),
        'params' => ['code'],
        'auth' => true,
    ],

    'payment_link' => ['name' => 'bj_emi_due', 'params' => ['name', 'amount', 'seq', 'count', 'due', 'link']],
    'class_scheduled' => ['name' => 'bj_class_scheduled', 'params' => ['name', 'title', 'batch', 'when']],
    'class_reminder' => ['name' => 'bj_class_starting', 'params' => ['name', 'title', 'window', 'link']],
    'bootcamp_payment_nudge' => ['name' => 'bj_payment_nudge', 'params' => ['name', 'days', 'course']],
    'mentor_booked' => ['name' => 'bj_mentor_booked', 'params' => ['name', 'with', 'when']],
];
