<?php

declare(strict_types=1);

/*
| Assignment submission + AI grading (PRD §6.5). Uploads store on the `s3` disk
| (MinIO locally). The grading prompt is a versioned file (resources/prompts).
*/
return [
    'upload' => [
        'max_mb' => (int) env('ASSIGNMENT_UPLOAD_MAX_MB', 20),
        'mimes' => ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg', 'jpeg', 'txt'],
    ],

    'grading' => [
        'prompt_version' => (int) env('AI_GRADING_PROMPT_VERSION', 1),
        'max_tokens' => (int) env('AI_GRADING_MAX_TOKENS', 1200),
    ],
];
