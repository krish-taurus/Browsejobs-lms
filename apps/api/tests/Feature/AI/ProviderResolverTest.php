<?php

declare(strict_types=1);

use App\Support\AI\ProviderResolver;

function resolver(): ProviderResolver
{
    return new ProviderResolver;
}

beforeEach(function () {
    // Start from a clean slate: no provider has a key.
    foreach (['anthropic', 'openai', 'kimi', 'deepseek', 'grok', 'custom'] as $p) {
        config(["ai.providers.{$p}.api_key" => '']);
    }
    config(['ai.providers.custom.base_url' => '']);
});

it('honours an explicit provider that has a key', function () {
    config(['ai.provider' => 'kimi', 'ai.providers.kimi.api_key' => 'sk-kimi']);

    expect(resolver()->resolve())->toBe('kimi')
        ->and(resolver()->active())->toBe('kimi');
});

it('falls back to a configured provider when the chosen one is keyless', function () {
    // Provider is still the default anthropic, but only Kimi has a key.
    config(['ai.provider' => 'anthropic', 'ai.providers.kimi.api_key' => 'sk-kimi']);

    expect(resolver()->resolve())->toBe('kimi')
        ->and(resolver()->active())->toBe('kimi');
});

it('auto-selects the first configured provider', function () {
    config(['ai.provider' => 'auto', 'ai.providers.deepseek.api_key' => 'sk-ds']);

    expect(resolver()->resolve())->toBe('deepseek');
});

it('prefers the earliest configured provider in order when several have keys', function () {
    config([
        'ai.provider' => 'auto',
        'ai.providers.kimi.api_key' => 'sk-kimi',
        'ai.providers.openai.api_key' => 'sk-oai',
    ]);

    // Order is anthropic, openai, kimi… — openai wins over kimi.
    expect(resolver()->resolve())->toBe('openai');
});

it('reports no active provider when nothing is configured', function () {
    config(['ai.provider' => 'auto']);

    expect(resolver()->active())->toBeNull()
        // resolve() still returns a buildable name so the client fails loudly at call time.
        ->and(resolver()->resolve())->toBe('anthropic');
});

it('requires a base URL as well as a key for custom', function () {
    config(['ai.provider' => 'auto', 'ai.providers.custom.api_key' => 'sk-custom']);
    expect(resolver()->isConfigured('custom'))->toBeFalse();

    config(['ai.providers.custom.base_url' => 'https://llm.internal/v1']);
    expect(resolver()->isConfigured('custom'))->toBeTrue();
});

it('exposes a UI status with the configured map', function () {
    config(['ai.provider' => 'auto', 'ai.providers.kimi.api_key' => 'sk-kimi']);

    $status = resolver()->status();

    expect($status['mode'])->toBe('auto')
        ->and($status['active'])->toBe('kimi')
        ->and(collect($status['providers'])->firstWhere('id', 'kimi')['configured'])->toBeTrue()
        ->and(collect($status['providers'])->firstWhere('id', 'grok')['configured'])->toBeFalse();
});
