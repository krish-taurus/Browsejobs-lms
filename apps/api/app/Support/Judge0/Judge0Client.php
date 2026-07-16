<?php

declare(strict_types=1);

namespace App\Support\Judge0;

/**
 * Judge0 code execution (PRD §6.5). Called from the interactive run flow; tests
 * bind {@see FakeJudge0Client} (CLAUDE.md: never call external APIs in tests).
 */
interface Judge0Client
{
    public function execute(int $languageId, string $source, ?string $stdin = null): Judge0Result;
}
