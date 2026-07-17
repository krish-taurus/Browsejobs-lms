<?php

declare(strict_types=1);

/*
| Content AI (PRD §6.10): manual transcript → AI study notes → AI Tutor KB feed.
| Transcripts are text (paste or a small .txt/.vtt/.md upload); no audio-to-text.
*/
return [
    'max_transcript_chars' => (int) env('CONTENT_MAX_TRANSCRIPT_CHARS', 60000),

    'upload' => [
        'max_mb' => (int) env('CONTENT_UPLOAD_MAX_MB', 5),
        'mimes' => ['txt', 'vtt', 'md'],
    ],

    'notes' => [
        'prompt_version' => (int) env('AI_NOTES_PROMPT_VERSION', 1),
        'max_tokens' => (int) env('AI_NOTES_MAX_TOKENS', 1200),
        'max_chars' => (int) env('CONTENT_NOTES_MAX_CHARS', 8000),
    ],
];
