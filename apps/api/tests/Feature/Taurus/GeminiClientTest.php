<?php

declare(strict_types=1);

use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\GeminiClient;
use App\Services\AI\OpenAiCompatibleClient;
use App\Support\AI\ClientFactory;
use App\Support\AI\ProviderNotConfigured;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function geminiClient(): GeminiClient
{
    return new GeminiClient([
        'api_key' => 'gemini-unit-key',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'model' => 'gemini-2.5-flash',
    ]);
}

it('speaks the generateContent dialect and parses text plus usage', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => 'Namaste '], ['text' => 'from Gemini']]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 21, 'candidatesTokenCount' => 7, 'totalTokenCount' => 28],
        'modelVersion' => 'gemini-2.5-flash-001',
    ])]);

    $result = geminiClient()->complete(new AiMessage(user: 'Say hi', system: 'Be brief', maxTokens: 64));

    expect($result->text)->toBe('Namaste from Gemini')
        ->and($result->promptTokens)->toBe(21)
        ->and($result->completionTokens)->toBe(7)
        ->and($result->model)->toBe('gemini-2.5-flash-001')
        ->and($result->stopReason)->toBe('end_turn');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-unit-key')
            && $request['contents'][0]['parts'][0]['text'] === 'Say hi'
            && $request['systemInstruction']['parts'][0]['text'] === 'Be brief'
            && $request['generationConfig']['maxOutputTokens'] === 64;
    });
});

it('uses the message model over the configured one and omits an absent system prompt', function (): void {
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'ok']]], 'finishReason' => 'MAX_TOKENS']],
    ])]);

    $result = geminiClient()->complete(new AiMessage(user: 'x', model: 'gemini-2.5-pro'));

    expect($result->stopReason)->toBe('length')
        ->and($result->model)->toBe('gemini-2.5-pro')
        ->and($result->promptTokens)->toBe(0);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/models/gemini-2.5-pro:generateContent')
        && ! isset($request['systemInstruction']));
});

it('throws on an HTTP error so the gateway logs a failure', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    expect(fn () => geminiClient()->complete(new AiMessage(user: 'x')))->toThrow(RequestException::class);
});

it('is the driver for the gemini provider, and groq rides the OpenAI dialect', function (): void {
    config(['ai.providers.gemini.api_key' => 'g-key', 'ai.providers.groq.api_key' => 'gsk-key']);

    expect(ClientFactory::for('gemini'))->toBeInstanceOf(GeminiClient::class)
        ->and(ClientFactory::for('groq'))->toBeInstanceOf(OpenAiCompatibleClient::class);

    config(['ai.provider' => 'gemini']);
    expect(app(AiClient::class))->toBeInstanceOf(GeminiClient::class);
});

it('refuses to build a client for a provider without a key', function (): void {
    config(['ai.providers.groq.api_key' => '']);

    expect(fn () => ClientFactory::for('groq'))->toThrow(ProviderNotConfigured::class);
    expect(fn () => ClientFactory::for('skynet'))->toThrow(ProviderNotConfigured::class);
});

it('builds a client from a workspace\'s own credentials without reading the platform key', function (): void {
    config([
        'ai.providers.openai.api_key' => 'sk-PLATFORM-should-not-be-used',
        'ai.providers.openai.base_url' => 'https://platform-proxy.example.test/v1',
    ]);
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
    ])]);

    ClientFactory::fromCredentials('openai', 'sk-WORKSPACE-own-key', null, null)->complete(new AiMessage(user: 'x'));

    // The vendor's own endpoint and the workspace's own key — not the platform's proxy or key.
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.openai.com/v1/')
        && $request->hasHeader('Authorization', 'Bearer sk-WORKSPACE-own-key'));
});

it('refuses workspace credentials without a key, or a custom provider without an endpoint', function (): void {
    expect(fn () => ClientFactory::fromCredentials('openai', '  '))->toThrow(ProviderNotConfigured::class)
        ->and(fn () => ClientFactory::fromCredentials('custom', 'k-123456789'))->toThrow(ProviderNotConfigured::class)
        ->and(ClientFactory::fromCredentials('custom', 'k-123456789', 'https://llm.example.test/v1', 'm'))->toBeInstanceOf(OpenAiCompatibleClient::class);
});
