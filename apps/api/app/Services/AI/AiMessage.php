<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A single-turn prompt for the AI transport (CLAUDE.md AI Service Layer).
 */
final readonly class AiMessage
{
    public function __construct(
        public string $user,
        public ?string $system = null,
        public ?string $model = null,
        public ?int $maxTokens = null,
    ) {}
}
