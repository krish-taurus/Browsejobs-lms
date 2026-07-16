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
