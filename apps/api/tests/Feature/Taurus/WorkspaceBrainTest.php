<?php

declare(strict_types=1);

use App\Models\AiEvent;
use App\Models\AuditLog;
use App\Models\TaurusAgent;
use App\Models\TaurusWorkspaceCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TaurusFixtures;

beforeEach(function (): void {
    TaurusFixtures::cleanPlatform();
    Http::preventStrayRequests();

    $this->tenant = Tenant::factory()->create();
    $this->client = TaurusFixtures::workspace($this->tenant, ['name' => 'Acme Ops']);
    $this->owner = User::factory()->for($this->tenant)->create(['user_type' => 'client']);
    TaurusFixtures::member($this->client, $this->owner, 'owner');
    $this->base = "/api/v1/taurus/workspaces/{$this->client->id}";

    $this->hq = TaurusFixtures::workspace($this->tenant, ['name' => 'Taurus HQ', 'is_owner' => true, 'brain_provider' => 'platform']);
    $this->founder = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    TaurusFixtures::member($this->hq, $this->founder, 'owner');
    $this->hqBase = "/api/v1/taurus/workspaces/{$this->hq->id}";
});

/** Platform keys the founder holds — a client workspace must never reach them. */
function givePlatformKeys(): void
{
    config([
        'ai.providers.openai.api_key' => 'sk-PLATFORM-openai-0000',
        'ai.providers.gemini.api_key' => 'PLATFORM-gemini-0000',
        'services.elevenlabs.api_key' => 'xi-PLATFORM-voice-0000',
        'services.elevenlabs.voice_id' => 'platform-voice',
    ]);
}

it('shows the brain panel without any secret', function (): void {
    TaurusFixtures::credential($this->client, 'gemini', 'AIza-client-secret-7788', ['model' => 'gemini-2.5-pro']);
    TaurusFixtures::credential($this->client, 'elevenlabs', 'xi-client-secret-5566', ['voice_id' => 'v-1']);
    $token = TaurusFixtures::token($this->client);

    Sanctum::actingAs($this->owner);
    $response = $this->getJson("{$this->base}/brain")->assertOk();

    foreach (['AIza-client-secret-7788', 'xi-client-secret-5566', $token] as $secret) {
        expect($response->getContent())->not->toContain($secret);
    }

    $gemini = collect($response->json('data.providers'))->firstWhere('id', 'gemini');
    expect($gemini)->toBe([
        'id' => 'gemini',
        'label' => 'Google Gemini',
        'configured' => true,
        'mask' => '••••7788',
        'model' => 'gemini-2.5-pro',
        'base_url' => null,
        'default_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'needs_base_url' => false,
        'platform_configured' => false,
    ]);

    $response->assertJsonPath('data.brain', [
        'provider' => null,
        'model' => null,
        'allows_platform' => false,
        'active' => ['provider' => 'gemini', 'model' => 'gemini-2.5-pro', 'source' => 'workspace'],
    ])
        ->assertJsonPath('data.voice', ['configured' => true, 'source' => 'workspace', 'mask' => '••••5566', 'voice_id' => 'v-1', 'model' => null])
        ->assertJsonPath('data.ingest.configured', true)
        ->assertJsonPath('data.ingest.mask', '••••'.substr($token, -4))
        ->assertJsonPath('data.ingest.endpoint', url('/api/v1/taurus/ingest'));
});

it('saves its own keys encrypted and masked; a blank key keeps the old one', function (): void {
    Sanctum::actingAs($this->owner);
    $this->putJson("{$this->base}/brain", [
        'brain_provider' => 'groq',
        'brain_model' => 'llama-3.3-70b-versatile',
        'providers' => ['groq' => ['api_key' => 'gsk-client-secret-4321']],
        'voice' => ['api_key' => 'xi-client-secret-8765', 'voice_id' => 'voice-abc'],
    ])->assertOk()
        ->assertJsonPath('data.brain.active.provider', 'groq')
        ->assertJsonPath('data.brain.active.source', 'workspace')
        ->assertJsonPath('data.voice.configured', true);

    $row = TaurusWorkspaceCredential::withoutGlobalScopes()->where('provider', 'groq')->sole();
    expect($row->api_key)->toBe('gsk-client-secret-4321')
        ->and($row->key_last4)->toBe('4321')
        ->and(DB::table('taurus_workspace_credentials')->where('id', $row->id)->value('api_key'))->not->toContain('gsk-client-secret-4321')
        ->and($row->toArray())->not->toHaveKey('api_key');

    $body = $this->putJson("{$this->base}/brain", ['providers' => ['groq' => ['api_key' => '', 'model' => 'llama-3.1-8b-instant']]])
        ->assertOk()->getContent();
    expect($body)->not->toContain('gsk-client-secret-4321')
        ->and($row->refresh()->api_key)->toBe('gsk-client-secret-4321')
        ->and($row->model)->toBe('llama-3.1-8b-instant');

    expect(AuditLog::query()->where('action', 'taurus.brain.updated')->latest('id')->first()->metadata['fields'])
        ->toContain('groq.model')
        ->and(json_encode(AuditLog::query()->pluck('metadata')))->not->toContain('gsk-client-secret-4321');
});

it('allows the platform brain for HQ only', function (): void {
    Sanctum::actingAs($this->owner);
    $this->putJson("{$this->base}/brain", ['brain_provider' => 'platform'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('brain_provider');
    expect($this->client->refresh()->brain_provider)->toBeNull();

    Sanctum::actingAs($this->founder);
    $this->putJson("{$this->hqBase}/brain", ['brain_provider' => 'platform'])
        ->assertOk()
        ->assertJsonPath('data.brain.allows_platform', true)
        ->assertJsonPath('data.brain.provider', 'platform');
});

it('gives a client workspace without a key no brain — and never touches platform keys', function (): void {
    givePlatformKeys();
    $platform = new FakeAiClient;
    app()->instance(AiClient::class, $platform);

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/ask", ['question' => 'What needs me?'])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'brain_not_configured');

    // Even pointing its brain at a provider the PLATFORM has a key for.
    $this->putJson("{$this->base}/brain", ['brain_provider' => 'openai'])->assertOk()
        ->assertJsonPath('data.brain.active', null);
    $this->postJson("{$this->base}/ask", ['question' => 'What needs me?'])->assertStatus(503);

    expect($platform->calls)->toBe([])
        ->and(AiEvent::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('answers a client with its own key, not the platform\'s or a neighbour\'s', function (): void {
    givePlatformKeys();
    $neighbour = TaurusFixtures::workspace($this->tenant, ['name' => 'Neighbour']);
    TaurusFixtures::credential($neighbour, 'openai', 'sk-NEIGHBOUR-openai-1111');
    TaurusFixtures::credential($this->client, 'openai', 'sk-CLIENT-openai-2222');
    $this->client->forceFill(['brain_provider' => 'openai'])->save();

    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => 'Nothing is waiting.'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 4],
    ])]);

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/ask", ['question' => 'Anything?'])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Nothing is waiting.')
        ->assertJsonPath('data.provider', 'openai');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-CLIENT-openai-2222'));
    Http::assertNotSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk-PLATFORM-openai-0000')
        || $request->hasHeader('Authorization', 'Bearer sk-NEIGHBOUR-openai-1111'));

    expect(AiEvent::withoutGlobalScopes()->sole()->purpose->value)->toBe('taurus');
});

it('lets HQ ask through the platform brain', function (): void {
    config(['ai.providers.openai.api_key' => 'sk-PLATFORM-openai-0000']);
    $fake = new FakeAiClient;
    $fake->reply = 'One approval is waiting.';
    app()->instance(AiClient::class, $fake);

    Sanctum::actingAs($this->founder);
    $this->postJson("{$this->hqBase}/ask", ['question' => 'What needs me?', 'floor' => 'ops'])
        ->assertOk()
        ->assertJsonPath('data.answer', 'One approval is waiting.')
        ->assertJsonPath('data.provider', 'openai');

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]->user)->toContain('What needs me?')
        ->and($fake->calls[0]->user)->toContain('Approvals waiting: 0.');
});

it('tests a workspace key with one tiny call, and reports failures by HTTP status only', function (): void {
    TaurusFixtures::credential($this->client, 'openai', 'sk-CLIENT-openai-3333');
    TaurusFixtures::credential($this->client, 'gemini', 'CLIENT-gemini-key-3333');
    TaurusFixtures::credential($this->client, 'elevenlabs', 'xi-CLIENT-voice-3333', ['voice_id' => 'v1']);

    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['model' => 'gpt-4o-mini', 'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 1]])
            ->push(['error' => ['message' => 'Incorrect API key provided: sk-CLIENT-openai-3333']], 401),
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'OK']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 7, 'candidatesTokenCount' => 1],
        ]),
        'api.elevenlabs.io/v1/voices' => Http::sequence()
            ->push(['voices' => [['voice_id' => 'v1']]])
            ->push(['detail' => 'invalid_api_key'], 401),
    ]);

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/brain/test", ['target' => 'openai'])->assertOk()->assertJsonPath('data.ok', true);
    $failed = $this->postJson("{$this->base}/brain/test", ['target' => 'openai'])->assertOk();
    expect($failed->json('data.ok'))->toBeFalse()
        ->and($failed->json('data.message'))->toContain('401')
        ->and($failed->getContent())->not->toContain('sk-CLIENT-openai-3333');

    $this->postJson("{$this->base}/brain/test", ['target' => 'gemini'])->assertOk()->assertJsonPath('data.ok', true);
    $this->postJson("{$this->base}/brain/test", ['target' => 'elevenlabs'])->assertOk()->assertJsonPath('data.ok', true);
    $voiceFail = $this->postJson("{$this->base}/brain/test", ['target' => 'elevenlabs'])->assertOk();
    expect($voiceFail->json('data.ok'))->toBeFalse()->and($voiceFail->json('data.message'))->toContain('401');

    Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'CLIENT-gemini-key-3333'));
});

it('never tests a client workspace with platform keys', function (): void {
    givePlatformKeys();

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/brain/test", ['target' => 'openai'])
        ->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.latency_ms', 0);
    $this->postJson("{$this->base}/brain/test", ['target' => 'elevenlabs'])
        ->assertOk()->assertJsonPath('data.ok', false);

    Http::assertNothingSent();
});

it('clears a workspace key', function (): void {
    TaurusFixtures::credential($this->client, 'openai', 'sk-CLIENT-openai-4444');

    Sanctum::actingAs($this->owner);
    $data = $this->deleteJson("{$this->base}/brain/providers/openai")->assertOk()->json('data');

    expect(collect($data['providers'])->firstWhere('id', 'openai')['configured'])->toBeFalse()
        ->and($data['brain']['active'])->toBeNull()
        ->and(TaurusWorkspaceCredential::withoutGlobalScopes()->count())->toBe(0);

    $this->deleteJson("{$this->base}/brain/providers/skynet")->assertNotFound();
});

it('rotates the ingest token, shows it once, and the new token reaches only this workspace', function (): void {
    Sanctum::actingAs($this->owner);
    $data = $this->postJson("{$this->base}/ingest-token")->assertOk()->json('data');

    expect($data['token'])->toStartWith('tau_')
        ->and(strlen($data['token']))->toBe(44)
        ->and($data['endpoint'])->toBe(url('/api/v1/taurus/ingest'))
        ->and($this->client->refresh()->ingest_token_hash)->toBe(hash('sha256', $data['token']))
        ->and(AuditLog::query()->where('action', 'taurus.ingest_token.rotated')->exists())->toBeTrue();

    expect($this->getJson("{$this->base}/brain")->getContent())->not->toContain($data['token']);

    $this->withHeaders(['Authorization' => 'Bearer '.$data['token']])
        ->postJson('/api/v1/taurus/ingest', ['events' => [['agent' => ['slug' => 'probe']]]])
        ->assertStatus(202);

    expect(TaurusAgent::withoutGlobalScopes()->sole()->taurus_workspace_id)->toBe($this->client->id);
});

it('speaks with the workspace voice; a client without one gets 204 even when the platform has a voice', function (): void {
    givePlatformKeys();
    Storage::fake('local');
    Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response('MP3BYTES', 200, ['Content-Type' => 'audio/mpeg'])]);

    Sanctum::actingAs($this->owner);
    $this->postJson("{$this->base}/speak", ['text' => 'All clear.'])->assertNoContent();
    Http::assertNothingSent();

    TaurusFixtures::credential($this->client, 'elevenlabs', 'xi-CLIENT-voice-5555', ['voice_id' => 'client-voice']);
    $response = $this->post("{$this->base}/speak", ['text' => 'All clear.'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
    expect($response->getContent())->toBe('MP3BYTES');

    Http::assertSent(fn ($request): bool => $request->hasHeader('xi-api-key', 'xi-CLIENT-voice-5555')
        && str_contains($request->url(), '/text-to-speech/client-voice'));
    Http::assertNotSent(fn ($request): bool => $request->hasHeader('xi-api-key', 'xi-PLATFORM-voice-0000'));
});

it('lets HQ fall back to the platform voice', function (): void {
    givePlatformKeys();
    Storage::fake('local');
    Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response('HQMP3', 200, ['Content-Type' => 'audio/mpeg'])]);

    Sanctum::actingAs($this->founder);
    $this->post("{$this->hqBase}/speak", ['text' => 'Morning.'], ['Accept' => 'application/json'])->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader('xi-api-key', 'xi-PLATFORM-voice-0000'));
});
