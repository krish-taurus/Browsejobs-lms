<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use RuntimeException;

/**
 * Default transport when no voice provider is configured: starting a voice
 * mock fails loudly (and the consumed credit is refunded by the action)
 * instead of pretending a call exists.
 */
final class NullVoiceMockClient implements VoiceMockClient
{
    public function createSession(MockInterview $interview, MockBlueprint $blueprint, array $opts): VoiceSession
    {
        throw new RuntimeException('No voice provider configured — set VAPI_API_KEY (services.vapi) to enable voice mocks.');
    }
}
