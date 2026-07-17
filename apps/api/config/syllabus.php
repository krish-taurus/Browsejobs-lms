<?php

declare(strict_types=1);

/*
| AI Syllabus Generator (PRD §6.2). AI drafts a course syllabus from curriculum
| data; a trainer approves it; it renders to a branded document on the render disk
| (branded HTML now — a WeasyPrint/PDF binarizer swaps in behind SyllabusRenderer
| later, exactly like certificates/receipts). Downloads are private, served via
| short-lived signed URLs.
*/
return [
    'render_disk' => env('SYLLABUS_RENDER_DISK', 's3'),

    'ai' => [
        'prompt_version' => (int) env('AI_SYLLABUS_PROMPT_VERSION', 1),
        'max_tokens' => (int) env('AI_SYLLABUS_MAX_TOKENS', 2000),
        'max_chars' => (int) env('SYLLABUS_MAX_CHARS', 12000),
    ],

    'download' => [
        'signed_url_ttl_min' => (int) env('SYLLABUS_SIGNED_URL_TTL_MIN', 60),
    ],
];
