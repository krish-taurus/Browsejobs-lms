<?php

declare(strict_types=1);

use App\Services\AI\AiClient;
use App\Services\AI\AiMessage;
use App\Services\AI\AnthropicClient;
use App\Services\AI\OpenAiCompatibleClient;
use Illuminate\Support\Facades\Http;

it('binds the driver matching AI_PROVIDER', function (string $provider, string $expected) {
    config(['ai.provider' => $provider]);

    expect(app(AiClient::class))->toBeInstanceOf($expected);
})->with([
    ['anthropic', AnthropicClient::class],
    ['openai', OpenAiCompatibleClient::class],
    ['kimi', OpenAiCompatibleClient::class],
    ['deepseek', OpenAiCompatibleClient::class],
    ['grok', OpenAiCompatibleClient::class],
    ['custom', OpenAiCompatibleClient::class],
]);

it('rejects an unknown provider with a clear error', function () {
    config(['ai.provider' => 'skynet']);

    expect(fn () => app(AiClient::class))->toThrow(RuntimeException::class, 'skynet');
});

it('speaks the OpenAI chat-completions dialect and parses the reply', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'model' => 'deepseek-chat',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Namaste from DeepSeek'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 21, 'completion_tokens' => 7],
        ]),
    ]);

    config(['ai.provider' => 'deepseek', 'ai.providers.deepseek.api_key' => 'sk-test']);

    $result = app(AiClient::class)->complete(new AiMessage(
        user: 'Say hello',
        system: 'You are the BrowseJobs tutor.',
    ));

    expect($result->text)->toBe('Namaste from DeepSeek')
        ->and($result->promptTokens)->toBe(21)
        ->and($result->completionTokens)->toBe(7)
        ->and($result->model)->toBe('deepseek-chat')
        ->and($result->stopReason)->toBe('stop');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.deepseek.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'deepseek-chat'
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Say hello'];
    });
});

it('sends kimi-k3 to the Moonshot endpoint by default', function () {
    Http::fake([
        'api.moonshot.ai/*' => Http::response([
            'model' => 'kimi-k3',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Hello from Kimi'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4],
        ]),
    ]);

    config(['ai.provider' => 'kimi', 'ai.providers.kimi.api_key' => 'sk-kimi-test']);

    $result = app(AiClient::class)->complete(new AiMessage(user: 'Say hello'));

    expect($result->text)->toBe('Hello from Kimi')
        ->and($result->model)->toBe('kimi-k3');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.moonshot.ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-kimi-test')
            && $request['model'] === 'kimi-k3';
    });
});

it('honours a per-call model override on the openai-compatible driver', function () {
    Http::fake(['api.x.ai/*' => Http::response([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ])]);

    config(['ai.provider' => 'grok', 'ai.providers.grok.api_key' => 'xai-test']);

    app(AiClient::class)->complete(new AiMessage(user: 'hi', model: 'grok-3-mini'));

    Http::assertSent(fn ($request) => $request['model'] === 'grok-3-mini');
});
