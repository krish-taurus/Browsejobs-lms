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

it('tests every configured provider and names the one that works', function (): void {
    // The exact shape of "I added a new key and nothing changed": an older
    // provider still holds a key, so the resolver keeps choosing it, and its
    // failure is the only thing anyone sees.
    config([
        'ai.provider' => 'anthropic',
        'ai.providers.anthropic.api_key' => 'stale-key',
        'ai.providers.deepseek.api_key' => 'fresh-key',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401),
        'api.deepseek.com/*' => Http::response([
            'model' => 'deepseek-chat',
            'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 1],
        ]),
    ]);

    $this->artisan('ai:doctor --all')
        ->expectsOutputToContain('anthropic')
        ->expectsOutputToContain('deepseek')
        // The point of the flag: say plainly that the active one is the
        // broken one while a working key sits unused. Asserted on the plain
        // line rather than the styled warning, which renders separately.
        ->expectsOutputToContain('Set Active provider in Admin')
        ->assertFailed();
});

it('passes --all when the active provider is the one that works', function (): void {
    config([
        'ai.provider' => 'deepseek',
        'ai.providers.anthropic.api_key' => '',
        'ai.providers.deepseek.api_key' => 'fresh-key',
    ]);

    Http::fake(['api.deepseek.com/*' => Http::response([
        'model' => 'deepseek-chat',
        'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 1],
    ])]);

    $this->artisan('ai:doctor --all')->assertSuccessful();
});

it('prints the reply verbatim and says whether it parses', function (): void {
    config(['ai.provider' => 'deepseek', 'ai.providers.deepseek.api_key' => 'k']);

    // A model that pads its JSON with reasoning — invisible today, because
    // ai_events stores tokens and cost but never the reply.
    Http::fake(['api.deepseek.com/*' => Http::response([
        'model' => 'deepseek-v4-flash',
        'choices' => [['message' => ['content' => "Let me think about this.\n\n{\"description\":\"A role.\"}"], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
    ])]);

    $this->artisan('ai:doctor --raw --max-tokens=100')
        ->expectsOutputToContain('--- reply begins ---')
        ->expectsOutputToContain('Let me think about this.')
        ->expectsOutputToContain('Parses as a JSON object: yes')
        ->assertSuccessful();
});

it('flags a reply that hit the token ceiling', function (): void {
    config(['ai.provider' => 'deepseek', 'ai.providers.deepseek.api_key' => 'k']);

    // Truncated mid-object: the exact shape of "the model answered and the
    // feature still did nothing".
    Http::fake(['api.deepseek.com/*' => Http::response([
        'model' => 'deepseek-v4-flash',
        'choices' => [['message' => ['content' => '{"description":"A role that'], 'finish_reason' => 'length']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 16],
    ])]);

    $this->artisan('ai:doctor --raw --max-tokens=16')
        ->expectsOutputToContain('Parses as a JSON object: NO')
        ->expectsOutputToContain('Re-run with a higher --max-tokens')
        ->assertSuccessful();
});

it('names the headroom a reasoning model needs', function (): void {
    config(['ai.provider' => 'deepseek', 'ai.providers.deepseek.api_key' => 'k']);

    // A short visible reply billed at three times its length: the tokens went
    // on reasoning that is never returned but counts against the ceiling. That
    // ratio is invisible everywhere else, and it is what sets the headroom.
    Http::fake(['api.deepseek.com/*' => Http::response([
        'model' => 'deepseek-v4-flash',
        'choices' => [['message' => ['content' => '{"a":1}'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 60],
    ])]);

    $this->artisan('ai:doctor --raw --max-tokens=500')
        ->expectsOutputToContain('Tokens billed vs visible')
        ->expectsOutputToContain('Set AI_TOKEN_HEADROOM')
        ->assertSuccessful();
});
