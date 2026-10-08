<?php

declare(strict_types=1);

return [

    /*
    | Staff notification. Per-type addresses fall back to ENQUIRY_NOTIFY_EMAIL,
    | then the published hello@ address. A confirmation to the person who
    | submitted is not sent.
    */
    'notify_employer' => env('ENQUIRY_NOTIFY_EMAIL_EMPLOYER') ?: env('ENQUIRY_NOTIFY_EMAIL', 'hello@browsejobs.ai'),
    'notify_course' => env('ENQUIRY_NOTIFY_EMAIL_COURSE') ?: env('ENQUIRY_NOTIFY_EMAIL', 'hello@browsejobs.ai'),

    /*
    | Shared secret so a Next.js proxy on another host can forward the real
    | client IP. Empty means only a loopback peer may set X-Enquiry-Client-Ip.
    */
    'proxy_secret' => env('ENQUIRY_PROXY_SECRET', ''),

    /*
    | Live course catalogue. Waitlist slugs (including agentic-ai) are rejected.
    */
    'courses' => [
        'data-engineering',
        'devops-cloud',
        'python-backend',
        'data-analytics',
    ],

];
