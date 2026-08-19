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

    // Seat confirmation. bj_batch_confirmed carries no login detail at all —
    // it tells them to sign in with their registered mobile number, which is
    // true however their account was created and can never go stale.
    'batch_credentials' => [
        'name' => env('WHATSAPP_BATCH_CREDENTIALS_TEMPLATE', 'bj_batch_confirmed'),
        'params' => ['name', 'batch_course', 'starts'],
    ],

    'otp' => [
        'name' => env('WHATSAPP_OTP_TEMPLATE', 'bj_otp'),
        'params' => ['code'],
        'auth' => true,
    ],

    'payment_link' => ['name' => 'bj_emi_due', 'params' => ['name', 'amount', 'seq', 'count', 'due', 'link']],
    'class_scheduled' => ['name' => 'bj_class_scheduled', 'params' => ['name', 'title', 'batch', 'when']],
    'class_reminder' => ['name' => 'bj_class_starting', 'params' => ['name', 'title', 'window', 'link']],
    // These two wait on Meta approval. Until then `class_rescheduled` goes out as
    // free text (24h window only) and the 5-minute rung falls back to the generic
    // approved reminder — see MessengerSessionNotifier::reminderKey().
    'class_reminder_5min' => ['name' => 'bj_class_starting_5min', 'params' => ['name', 'title', 'batch', 'time', 'link']],
    'class_rescheduled' => ['name' => 'bj_class_rescheduled', 'params' => ['name', 'title', 'batch', 'date', 'time']],
    'bootcamp_payment_nudge' => ['name' => 'bj_payment_nudge', 'params' => ['name', 'days', 'course']],
    'mentor_booked' => ['name' => 'bj_mentor_booked', 'params' => ['name', 'with', 'when']],
    // Two-round placement interview. The params order below IS the {{1}}, {{2}},
    // … order of the Meta templates created by whatsapp:create-interview-templates
    // — change one and you must change the other.
    'interview_applied' => [
        'name' => env('WHATSAPP_INTERVIEW_APPLIED_TEMPLATE', 'bj_interview_applied'),
        'params' => ['student', 'round', 'stage', 'slot', 'url'],
    ],

    'interview_approved' => [
        'name' => env('WHATSAPP_INTERVIEW_APPROVED_TEMPLATE', 'bj_interview_approved'),
        'params' => ['round', 'stage', 'slot', 'interviewer', 'url'],
    ],

    'interview_declined' => [
        'name' => env('WHATSAPP_INTERVIEW_DECLINED_TEMPLATE', 'bj_interview_declined'),
        'params' => ['round', 'stage', 'note', 'url'],
    ],

    'interview_rescheduled' => [
        'name' => env('WHATSAPP_INTERVIEW_RESCHEDULED_TEMPLATE', 'bj_interview_rescheduled'),
        'params' => ['round', 'stage', 'was', 'slot', 'interviewer', 'url'],
    ],

    'interview_cleared' => [
        'name' => env('WHATSAPP_INTERVIEW_CLEARED_TEMPLATE', 'bj_interview_cleared'),
        'params' => ['round', 'stage', 'note', 'next', 'url'],
    ],

    'interview_not_cleared' => [
        'name' => env('WHATSAPP_INTERVIEW_NOT_CLEARED_TEMPLATE', 'bj_interview_not_cleared'),
        'params' => ['round', 'stage', 'note', 'url'],
    ],
];