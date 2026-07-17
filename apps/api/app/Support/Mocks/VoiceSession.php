<?php

declare(strict_types=1);

namespace App\Support\Mocks;

/**
 * What a voice provider hands back when a session is created.
 */
final readonly class VoiceSession
{
    public function __construct(
        public string $sessionId,
        public string $joinUrl,
    ) {}
}
