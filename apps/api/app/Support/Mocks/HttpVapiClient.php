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
                    'model' => [
                        'provider' => 'anthropic',
                        'model' => (string) config('ai.providers.anthropic.model', 'claude-sonnet-5'),
                        'messages' => [['role' => 'system', 'content' => $opts['system_prompt']]],
                    ],
                    'server' => [
                        'url' => rtrim((string) config('app.url'), '/').'/api/webhooks/voice',
                        'secret' => $this->config['webhook_secret'],
                    ],
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
