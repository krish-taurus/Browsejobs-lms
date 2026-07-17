<?php

declare(strict_types=1);

namespace App\Support\Interviews;

use RuntimeException;

/**
 * Test double: returns a canned transcript (or throws when $shouldFail),
 * recording every call.
 */
final class FakeTranscriptionClient implements TranscriptionClient
{
    /** @var list<array{name: string, bytes: int}> */
    public array $calls = [];

    public function __construct(
        public string $transcript = 'INTERVIEWER: Tell me about yourself. CANDIDATE: I am a data engineer.',
        public bool $shouldFail = false,
    ) {}

    public function transcribe(string $contents, string $originalName): string
    {
        $this->calls[] = ['name' => $originalName, 'bytes' => strlen($contents)];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake transcription failure.');
        }

        return $this->transcript;
    }
}
