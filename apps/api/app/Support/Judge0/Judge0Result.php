<?php

declare(strict_types=1);

namespace App\Support\Judge0;

/**
 * A Judge0 execution result (PRD §6.5).
 */
final readonly class Judge0Result
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public string $compileOutput,
        public int $statusId,
        public string $statusText,
        public int $timeMs,
        public int $memoryKb,
    ) {}

    /** Judge0 status id 3 = "Accepted" (ran without error). */
    public function accepted(): bool
    {
        return $this->statusId === 3;
    }
}
