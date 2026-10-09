<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

/**
 * Driver for Google Gemini's generateContent API. Gemini does not speak the
 * OpenAI chat-completions dialect, so it gets its own driver; to every caller
 * it is just another AiClient. Only invoked via the AiGateway (or a provider
 * test); tests use Http::fake.
 */
final class GeminiClient implements AiClient
{
    /**
     * @param  array{api_key: string, base_url: string, model: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function complete(AiMessage $message): AiResult
    {
        $model = $message->model ?? $this->config['model'];

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $message->user]],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => $message->maxTokens ?? (int) config('ai.max_tokens', 1024),
            ],
        ];

        if ($message->system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $message->system]]];
        }

        $url = rtrim($this->config['base_url'], '/').'/models/'.rawurlencode($model).':generateContent';

        $response = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->timeout((int) config('ai.http_timeout', 120))
            ->acceptJson()
            ->post($url, $payload)
            ->throw()->json();

        $text = collect(data_get($response, 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(static fn ($part): bool => is_string($part))
            ->implode('');

        return new AiResult(
            text: $text,
            promptTokens: (int) data_get($response, 'usageMetadata.promptTokenCount', 0),
            completionTokens: (int) data_get($response, 'usageMetadata.candidatesTokenCount', 0),
            model: (string) ($response['modelVersion'] ?? $model),
            stopReason: $this->stopReason((string) data_get($response, 'candidates.0.finishReason', 'STOP')),
        );
    }

    /** Map Gemini's finish reasons onto the vocabulary consumers already check. */
    private function stopReason(string $finish): string
    {
        return match (strtoupper($finish)) {
            'MAX_TOKENS' => 'length',
            'STOP' => 'end_turn',
            default => strtolower($finish),
        };
    }
}
