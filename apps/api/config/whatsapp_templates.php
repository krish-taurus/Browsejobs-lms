<?php

declare(strict_types=1);

/*
 * Meta-approved WhatsApp template mapping: message key → the approved template
 * name on the WABA and the ORDER of its {{1}}..{{n}} body parameters (each
 * entry names a var passed to Messenger::send for that key).
 *
 * Activation is automatic: `whatsapp:sync-templates` (hourly) checks Meta and
 * writes the template name onto message_templates.name only once APPROVED —
 * until then WhatsApp sends stay free-form session texts (24h window).
 */
return [
    // AUTHENTICATION category — the body {{1}} and the copy-code button both
    // take the code (the client adds the button component for auth templates).
    'otp' => ['name' => 'bj_otp', 'params' => ['code'], 'auth' => true],
    'payment_link' => ['name' => 'bj_emi_due', 'params' => ['name', 'amount', 'seq', 'count', 'due', 'link']],
    'batch_credentials' => ['name' => 'bj_batch_joined', 'params' => ['name', 'batch_course', 'starts']],
    'class_scheduled' => ['name' => 'bj_class_scheduled', 'params' => ['name', 'title', 'batch', 'when']],
    'class_reminder' => ['name' => 'bj_class_starting', 'params' => ['name', 'title', 'window', 'link']],
    'bootcamp_payment_nudge' => ['name' => 'bj_payment_nudge', 'params' => ['name', 'days', 'course']],
    'mentor_booked' => ['name' => 'bj_mentor_booked', 'params' => ['name', 'with', 'when']],
];
