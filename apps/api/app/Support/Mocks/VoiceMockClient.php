<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use App\Models\MockBlueprint;
use App\Models\MockInterview;

/**
 * Swappable voice-interview transport (PRD §6.6 — Vapi/Retell class of
 * providers). Bound in AppServiceProvider from services.vapi; tests bind
 * FakeVoiceMockClient. The provider must call back webhooks/voice at end of
 * call with the transcript, duration and cost.
 */
interface VoiceMockClient
{
    /**
     * @param  array{max_seconds: int, wrapup_seconds: int, system_prompt: string}  $opts
     *
     * @throws \RuntimeException when the provider is unavailable.
     */
    public function createSession(MockInterview $interview, MockBlueprint $blueprint, array $opts): VoiceSession;
}
