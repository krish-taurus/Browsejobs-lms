<?php

declare(strict_types=1);

/*
| Enquiry alerts. Every value has a code default so `php artisan optimize`
| can cache config when these variables are absent from .env.
|
| env() is read only in this file. Callers use config('enquiry.*').
*/

$hello = 'hello@browsejobs.ai';
$shared = env('ENQUIRY_NOTIFY_EMAIL', $hello);
if (! is_string($shared) || filter_var($shared, FILTER_VALIDATE_EMAIL) === false) {
    $shared = $hello;
}

$employer = env('ENQUIRY_NOTIFY_EMAIL_EMPLOYER');
$course = env('ENQUIRY_NOTIFY_EMAIL_COURSE');

return [

    'notify_employer' => is_string($employer) && filter_var($employer, FILTER_VALIDATE_EMAIL) ? $employer : $shared,

    'notify_course' => is_string($course) && filter_var($course, FILTER_VALIDATE_EMAIL) ? $course : $shared,

    /*
    | Optional. Empty on the single VPS: Next connects to Nginx on loopback,
    | and that peer may forward the visitor IP without a shared secret.
    */
    'proxy_secret' => (string) (env('ENQUIRY_PROXY_SECRET') ?: ''),

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
