<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * The AI transport's completion result + token usage.
 */
final readonly class AiResult
{
    public function __construct(
        public string $text,
        public int $promptTokens,
        public int $completionTokens,
        public string $model,
        public string $stopReason = 'end_turn',
    ) {}

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
