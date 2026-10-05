<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vapi transport for voice mocks. Creates a web call with an inline
 * assistant (interviewer prompt + hard duration cap) and our webhook as the
 * end-of-call report target. Retell or any similar provider slots in behind
 * the same interface.
 */
final readonly class HttpVapiClient implements VoiceMockClient
{
    /**
     * @param  array{api_key: string, base_url: string, webhook_secret: string}  $config
     */
    public function __construct(private array $config) {}

    public function createSession(MockInterview $interview, MockBlueprint $blueprint, array $opts): VoiceSession
    {
        $response = Http::withToken($this->config['api_key'])
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/call/web', [
                'metadata' => ['mock_interview_id' => $interview->id],
                'maxDurationSeconds' => $opts['max_seconds'],
                'assistant' => [
                    'firstMessage' => $blueprint->opening_question,
                    // The interviewer brain follows AI_PROVIDER (ADR 0016):
                    // OpenAI/Anthropic natively, Kimi/DeepSeek/Grok via custom-llm.
                    'model' => VoiceBrain::modelConfig($opts['system_prompt']),
                    'server' => [
                        'url' => rtrim((string) config('app.url'), '/').'/api/webhooks/voice',
                        'secret' => $this->config['webhook_secret'],
                    ],
                    // Employer-job mocks are the candidate's evidence to the
                    // hiring team (PRD-E), not just a scored transcript — the
                    // recording is what they open. General course mocks get
                    // one too; nothing currently plays it back for those, but
                    // recording is cheap and having it beats not having it.
                    'artifactPlan' => ['recordingEnabled' => true],
                    // Same ElevenLabs voice already used to speak the text-mode
                    // interviewer's questions (services.elevenlabs) — one voice
                    // for the interviewer everywhere, not a second one just for
                    // Vapi. Requires that ElevenLabs key to be registered as a
                    // provider key in the Vapi dashboard (Vapi calls ElevenLabs
                    // on our behalf; the key itself never goes in this payload).
                    // Omitted entirely when no voice id is set, so Vapi's own
                    // default voice is used rather than a broken reference.
                    ...(($voiceId = (string) config('services.elevenlabs.voice_id')) !== ''
                        ? ['voice' => ['provider' => '11labs', 'voiceId' => $voiceId]]
                        : []),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Voice provider error: '.substr($response->body(), 0, 200));
        }

        $id = (string) $response->json('id');
        $joinUrl = (string) ($response->json('webCallUrl') ?? $response->json('monitor.listenUrl') ?? '');

        if ($id === '') {
            throw new RuntimeException('Voice provider returned no session id.');
        }

        return new VoiceSession($id, $joinUrl);
    }
}
