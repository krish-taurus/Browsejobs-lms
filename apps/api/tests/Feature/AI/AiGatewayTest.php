<?php

declare(strict_types=1);

use App\Enums\AiPurpose;
use App\Models\AiEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiBudgetExceeded;
use App\Services\AI\AiClient;
use App\Services\AI\AiGateway;
use App\Services\AI\AiMessage;
use App\Services\AI\AiResult;
use App\Services\AI\FakeAiClient;
use App\Services\AI\PromptRepository;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('completes a call and logs an ai_event with tokens and cost', function () {
    $result = withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::Tutor, 'ping', 1, ['message' => 'hi']));

    expect($result->text)->toBe('This is a deterministic test reply.')
        ->and($this->fake->calls)->toHaveCount(1);

    $event = AiEvent::withoutGlobalScopes()->first();
    expect($event->purpose)->toBe(AiPurpose::Tutor)
        ->and($event->total_tokens)->toBe(60)          // 42 + 18
        ->and($event->cost_micros)->toBeGreaterThan(0)
        ->and($event->status->value)->toBe('ok');
});

it('enforces the per-student daily token budget', function () {
    config(['ai.daily_token_budget' => 100]);

    // First call spends 60 tokens (under 100) → ok.
    withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::General, 'ping', 1, ['message' => 'a']));

    // 60 already spent ≥ ... still under 100, so a second call is allowed then trips.
    withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::General, 'ping', 1, ['message' => 'b']));

    // Now 120 spent ≥ 100 → the next call is rejected + logged.
    expect(fn () => withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::General, 'ping', 1, ['message' => 'c'])))
        ->toThrow(AiBudgetExceeded::class);

    expect(AiEvent::withoutGlobalScopes()->where('status', 'budget_exceeded')->count())->toBe(1);
});

it('renders a versioned prompt with variables', function () {
    $rendered = app(PromptRepository::class)->render('ping', 1, ['message' => 'pong']);

    expect($rendered)->toContain('pong');
});

it('logs a failed event and rethrows when the transport errors', function () {
    app()->instance(AiClient::class, new class implements AiClient
    {
        public function complete(AiMessage $m): AiResult
        {
            throw new RuntimeException('upstream down');
        }
    });

    expect(fn () => withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::General, 'ping', 1, ['message' => 'x'])))
        ->toThrow(RuntimeException::class);

    expect(AiEvent::withoutGlobalScopes()->where('status', 'failed')->count())->toBe(1);
});

it('sends the active provider model, not the global anthropic default', function () {
    // Kimi is the only configured provider; the gateway must use its model so
    // Kimi is never handed "claude-sonnet-5" (which it would reject).
    config([
        'ai.provider' => 'auto',
        'ai.providers.kimi.api_key' => 'sk-kimi',
        'ai.providers.kimi.model' => 'kimi-k2-turbo-preview',
    ]);

    withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::Syllabus, 'ping', 1, ['message' => 'hi']));

    expect($this->fake->calls[0]->model)->toBe('kimi-k2-turbo-preview');
    expect(AiEvent::withoutGlobalScopes()->first()->model)->toBe('kimi-k2-turbo-preview');
});

it('still honours an explicit per-call model override', function () {
    config(['ai.provider' => 'auto', 'ai.providers.kimi.api_key' => 'sk-kimi']);

    withinTenant($this->tenant, fn () => app(AiGateway::class)->complete($this->student, AiPurpose::Syllabus, 'ping', 1, ['message' => 'hi'], ['model' => 'kimi-thinking']));

    expect($this->fake->calls[0]->model)->toBe('kimi-thinking');
});
