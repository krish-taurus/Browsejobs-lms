<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use RuntimeException;

/**
 * Test double: returns a canned session (or throws when $shouldFail),
 * recording every createSession call with its options.
 */
final class FakeVoiceMockClient implements VoiceMockClient
{
    /** @var list<array{interview_id: int, opts: array<string, mixed>}> */
    public array $calls = [];

    public function __construct(
        public string $sessionId = 'vapi-session-1',
        public string $joinUrl = 'https://voice.example/join/vapi-session-1',
        public bool $shouldFail = false,
    ) {}

    public function createSession(MockInterview $interview, MockBlueprint $blueprint, array $opts): VoiceSession
    {
        $this->calls[] = ['interview_id' => $interview->id, 'opts' => $opts];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake voice provider failure.');
        }

        return new VoiceSession($this->sessionId, $this->joinUrl);
    }
}
