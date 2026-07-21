<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(function () {
    foreach (['anthropic', 'openai', 'kimi', 'deepseek', 'grok', 'custom'] as $p) {
        config(["ai.providers.{$p}.api_key" => '']);
    }
});

it('reports when no provider is configured', function () {
    config(['ai.provider' => 'auto']);

    artisan('ai:doctor')->assertExitCode(1);
});

it('succeeds on a healthy provider', function () {
    Http::fake(['api.moonshot.ai/*' => Http::response([
        'model' => 'kimi-k2-turbo-preview',
        'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1],
    ])]);
    config(['ai.provider' => 'auto', 'ai.providers.kimi.api_key' => 'sk-kimi']);

    artisan('ai:doctor')->assertExitCode(0);
});

it('surfaces the provider error body on a rejected key', function () {
    Http::fake(['api.moonshot.ai/*' => Http::response(['error' => ['message' => 'Invalid Authentication']], 401)]);
    config(['ai.provider' => 'auto', 'ai.providers.kimi.api_key' => 'sk-bad']);

    artisan('ai:doctor')
        ->expectsOutputToContain('HTTP 401')
        ->assertExitCode(1);
});
