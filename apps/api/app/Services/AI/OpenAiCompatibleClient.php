<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

/**
 * Driver for any OpenAI-compatible chat-completions API — OpenAI itself, plus
 * Kimi (Moonshot), DeepSeek, Grok (xAI), Ollama, Together, etc. The provider is
 * just a base_url + api_key + default model in config/ai.php. Only invoked via
 * the AiGateway; unit tests use {@see FakeAiClient} or Http::fake.
 */
final class OpenAiCompatibleClient implements AiClient
{
    /**
     * @param  array{api_key: string, base_url: string, model: string, reasoning_effort?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function complete(AiMessage $message): AiResult
    {
        $model = $message->model ?? $this->config['model'];

        $messages = [];
        if ($message->system !== null) {
            $messages[] = ['role' => 'system', 'content' => $message->system];
        }
        $messages[] = ['role' => 'user', 'content' => $message->user];

        $payload = [
            'model' => $model,
            'max_tokens' => $message->maxTokens ?? (int) config('ai.max_tokens', 1024),
            'messages' => $messages,
        ];

        // Kimi k2.6+ are reasoning models: left alone they spend the whole
        // token budget "thinking" and return empty content with
        // finish_reason=length, which reads as the AI silently failing.
        if (($this->config['reasoning_effort'] ?? '') !== '') {
            $payload['reasoning_effort'] = $this->config['reasoning_effort'];
        }

        $response = Http::withToken($this->config['api_key'])
            ->timeout((int) config('ai.http_timeout', 120))
            ->acceptJson()
            ->post(rtrim($this->config['base_url'], '/').'/chat/completions', $payload)
            ->throw()->json();

        return new AiResult(
            text: (string) data_get($response, 'choices.0.message.content', ''),
            promptTokens: (int) data_get($response, 'usage.prompt_tokens', 0),
            completionTokens: (int) data_get($response, 'usage.completion_tokens', 0),
            model: (string) ($response['model'] ?? $model),
            stopReason: (string) data_get($response, 'choices.0.finish_reason', 'stop'),
        );
    }
}
