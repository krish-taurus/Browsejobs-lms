<?php

declare(strict_types=1);

/*
| Coding labs (PRD §6.5): Monaco + self-hosted Judge0. Maps our language keys to
| Judge0 CE language ids and sets execution limits. Tests fake the Judge0 client.
*/
return [
    'languages' => [
        'python' => (int) env('JUDGE0_LANG_PYTHON', 71),      // Python 3.8
        'javascript' => (int) env('JUDGE0_LANG_JS', 63),      // Node.js
        'bash' => (int) env('JUDGE0_LANG_BASH', 46),          // Bash
        'sql' => (int) env('JUDGE0_LANG_SQL', 82),            // SQLite
    ],
    'time_limit_ms' => (int) env('JUDGE0_TIME_LIMIT_MS', 5_000),
    'memory_kb' => (int) env('JUDGE0_MEMORY_KB', 128_000),
];
