<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

/**
 * Real Anthropic Messages API client. Only invoked via the AiGateway; unit tests
 * use {@see FakeAiClient} (CLAUDE.md: never call external APIs in tests).
 */
final class AnthropicClient implements AiClient
{
    /**
     * @param  array{api_key: string, base_url: string, version: string, model: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function complete(AiMessage $message): AiResult
    {
        $model = $message->model ?? $this->config['model'];

        $payload = array_filter([
            'model' => $model,
            'max_tokens' => $message->maxTokens ?? (int) config('ai.max_tokens', 1024),
            'system' => $message->system,
            'messages' => [['role' => 'user', 'content' => $message->user]],
        ], static fn ($v) => $v !== null);

        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => $this->config['version'],
        ])->acceptJson()->post($this->config['base_url'].'/messages', $payload)->throw()->json();

        $text = collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new AiResult(
            text: $text,
            promptTokens: (int) data_get($response, 'usage.input_tokens', 0),
            completionTokens: (int) data_get($response, 'usage.output_tokens', 0),
            model: (string) ($response['model'] ?? $model),
            stopReason: (string) ($response['stop_reason'] ?? 'end_turn'),
        );
    }
}
