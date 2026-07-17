<?php

declare(strict_types=1);

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Mocks\HttpVapiClient;
use App\Support\Mocks\VoiceBrain;
use Illuminate\Support\Facades\Http;

it('maps the anthropic provider to a native vapi anthropic brain', function () {
    config(['ai.provider' => 'anthropic', 'ai.providers.anthropic.model' => 'claude-sonnet-5']);

    $model = VoiceBrain::modelConfig('You are the interviewer.');

    expect($model['provider'])->toBe('anthropic')
        ->and($model['model'])->toBe('claude-sonnet-5')
        ->and($model['messages'][0])->toBe(['role' => 'system', 'content' => 'You are the interviewer.']);
});

it('maps the openai provider to a native vapi openai brain', function () {
    config(['ai.provider' => 'openai', 'ai.providers.openai.model' => 'gpt-4o']);

    $model = VoiceBrain::modelConfig('Prompt.');

    expect($model['provider'])->toBe('openai')
        ->and($model['model'])->toBe('gpt-4o')
        ->and($model)->not->toHaveKey('url');
});

it('routes kimi (and any openai-compatible vendor) through vapi custom-llm', function () {
    config([
        'ai.provider' => 'kimi',
        'ai.providers.kimi.model' => 'kimi-k2-turbo-preview',
        'ai.providers.kimi.base_url' => 'https://api.moonshot.ai/v1/',
    ]);

    $model = VoiceBrain::modelConfig('Prompt.');

    expect($model['provider'])->toBe('custom-llm')
        ->and($model['url'])->toBe('https://api.moonshot.ai/v1')
        ->and($model['model'])->toBe('kimi-k2-turbo-preview');
});

it('falls back to anthropic when the chosen provider is misconfigured', function () {
    config([
        'ai.provider' => 'custom',
        'ai.providers.custom.model' => '',
        'ai.providers.custom.base_url' => '',
        'ai.providers.anthropic.model' => 'claude-sonnet-5',
    ]);

    $model = VoiceBrain::modelConfig('Prompt.');

    expect($model['provider'])->toBe('anthropic')
        ->and($model['model'])->toBe('claude-sonnet-5');
});

it('sends the provider-mapped brain, duration cap, and webhook target in the vapi create call', function () {
    config([
        'ai.provider' => 'kimi',
        'ai.providers.kimi.model' => 'kimi-k2-turbo-preview',
        'ai.providers.kimi.base_url' => 'https://api.moonshot.ai/v1',
        'app.url' => 'https://lms.browsejobs.ai',
    ]);
    Http::fake(['api.vapi.test/*' => Http::response(['id' => 'call-99', 'webCallUrl' => 'https://vapi.daily.co/x'])]);

    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $blueprint = MockBlueprint::factory()->for($tenant)->create(['opening_question' => 'Tell me about your last project.']);
    $interview = withinTenant($tenant, fn () => MockInterview::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => 'voice', 'status' => MockInterview::STATUS_IN_PROGRESS, 'started_at' => now(),
    ]));

    $client = new HttpVapiClient([
        'api_key' => 'vapi-key', 'base_url' => 'https://api.vapi.test', 'webhook_secret' => 'hook-secret',
    ]);

    $session = $client->createSession($interview, $blueprint, [
        'max_seconds' => 600, 'wrapup_seconds' => 60, 'system_prompt' => 'You are the interviewer.',
    ]);

    expect($session->sessionId)->toBe('call-99')
        ->and($session->joinUrl)->toBe('https://vapi.daily.co/x');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_ends_with($request->url(), '/call/web')
            && $request->hasHeader('Authorization', 'Bearer vapi-key')
            && $body['maxDurationSeconds'] === 600
            && $body['assistant']['firstMessage'] === 'Tell me about your last project.'
            && $body['assistant']['model']['provider'] === 'custom-llm'
            && $body['assistant']['model']['model'] === 'kimi-k2-turbo-preview'
            && $body['assistant']['server']['url'] === 'https://lms.browsejobs.ai/api/webhooks/voice'
            && $body['assistant']['server']['secret'] === 'hook-secret';
    });
});

it('throws on a provider error so the caller can refund the credit', function () {
    Http::fake(['api.vapi.test/*' => Http::response(['error' => 'nope'], 500)]);

    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $blueprint = MockBlueprint::factory()->for($tenant)->create();
    $interview = withinTenant($tenant, fn () => MockInterview::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => 'voice', 'status' => MockInterview::STATUS_IN_PROGRESS, 'started_at' => now(),
    ]));

    $client = new HttpVapiClient([
        'api_key' => 'vapi-key', 'base_url' => 'https://api.vapi.test', 'webhook_secret' => 's',
    ]);

    $client->createSession($interview, $blueprint, [
        'max_seconds' => 600, 'wrapup_seconds' => 60, 'system_prompt' => 'P.',
    ]);
})->throws(RuntimeException::class);
