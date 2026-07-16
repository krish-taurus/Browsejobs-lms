<?php

declare(strict_types=1);

namespace App\Support\Judge0;

use Closure;

/**
 * In-memory Judge0 client for tests/dev. Records calls; returns a fixed stdout,
 * or delegates to a handler that can compute stdout from stdin (to exercise
 * per-test-case submissions).
 */
final class FakeJudge0Client implements Judge0Client
{
    /** @var list<array{languageId: int, source: string, stdin: ?string}> */
    public array $calls = [];

    public string $stdout = '';

    /** @var (Closure(int, string, ?string): Judge0Result)|null */
    public ?Closure $handler = null;

    public function execute(int $languageId, string $source, ?string $stdin = null): Judge0Result
    {
        $this->calls[] = ['languageId' => $languageId, 'source' => $source, 'stdin' => $stdin];

        if ($this->handler !== null) {
            return ($this->handler)($languageId, $source, $stdin);
        }

        return new Judge0Result(
            stdout: $this->stdout,
            stderr: '',
            compileOutput: '',
            statusId: 3,
            statusText: 'Accepted',
            timeMs: 12,
            memoryKb: 3000,
        );
    }
}
