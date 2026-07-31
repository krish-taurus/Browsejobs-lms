<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\EntitlementFeature;
use App\Models\AiEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\PointsEvent;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Entitlements\EntitlementService;
use App\Support\Mocks\FakeVoiceMockClient;
use App\Support\Mocks\VoiceMockClient;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Balances here are explicit test inputs — disable the first-touch free
    // allowance so "given N credits" means exactly N.
    config(['services.vapi.webhook_secret' => 'voice-secret', 'monetization.voice_mock.free_grants' => 0]);
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->voice = new FakeVoiceMockClient;
    app()->instance(VoiceMockClient::class, $this->voice);
    $this->tenant = Tenant::factory()->create();
});

/**
 * An enrolled student with a blueprint and the given voice-credit balance.
 */
function voiceReadyStudent(Tenant $tenant, int $credits = 1): User
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id,
        'status' => BatchMemberStatus::Enrolled->value,
    ]);
    MockBlueprint::factory()->for($tenant)->create([
        'course_id' => $course->id,
        'role_title' => 'Data Engineer',
        'competencies' => ['SQL', 'Communication'],
    ]);

    if ($credits > 0) {
        app(EntitlementService::class)->grantCredits(
            $student, EntitlementFeature::VoiceMock->value, $credits, 'test-grant',
        );
    }

    return $student;
}

function voiceBalance(User $student): int
{
    return app(EntitlementService::class)->balance($student, EntitlementFeature::VoiceMock->value);
}

/** A Vapi-shaped end-of-call webhook delivery. */
function voiceWebhook(string $sessionId, array $messages, float $cost = 0.42, int $duration = 480, string $type = 'end-of-call-report')
{
    return postJson('/api/webhooks/voice', [
        'message' => [
            'type' => $type,
            'call' => ['id' => $sessionId],
            'artifact' => ['messages' => $messages],
            'durationSeconds' => $duration,
            'cost' => $cost,
        ],
    ], ['x-vapi-secret' => 'voice-secret']);
}

function fullCallMessages(): array
{
    return [
        ['role' => 'system', 'message' => 'You are an interviewer.'],
        ['role' => 'assistant', 'message' => 'Tell me about a pipeline you built.'],
        ['role' => 'user', 'message' => 'I built a batch pipeline with Airflow and dbt.'],
        ['role' => 'assistant', 'message' => 'How did you handle late-arriving data?'],
        ['role' => 'user', 'message' => 'Watermarks plus a reprocessing window.'],
    ];
}

/* ---- Start ---- */

it('starts a voice session: consumes one credit, passes the 10-minute cap, and resumes without double-charging', function () {
    $student = voiceReadyStudent($this->tenant, credits: 2);
    Sanctum::actingAs($student);

    $first = postJson('/api/v1/me/mocks/voice')
        ->assertCreated()
        ->assertJsonPath('data.session_id', 'vapi-session-1')
        ->assertJsonPath('data.join_url', 'https://voice.example/join/vapi-session-1')
        ->assertJsonPath('data.max_seconds', 600);

    expect(voiceBalance($student))->toBe(1)
        ->and($this->voice->calls[0]['opts']['max_seconds'])->toBe(600)
        ->and($this->voice->calls[0]['opts']['system_prompt'])
        ->toContain('Data Engineer')
        ->toContain('wrap up gracefully');

    // Resume while the call is live: same session, wallet untouched.
    postJson('/api/v1/me/mocks/voice')->assertCreated()
        ->assertJsonPath('data.id', $first->json('data.id'));
    expect(voiceBalance($student))->toBe(1)
        ->and(count($this->voice->calls))->toBe(1);
});

it('answers 402 with one-tap top-up products when the wallet is empty', function () {
    foreach ([['voice-single', 24_900, 1], ['voice-3pack', 59_900, 3]] as [$sku, $price, $grant]) {
        Product::query()->create([
            'tenant_id' => $this->tenant->id, 'sku' => $sku, 'name' => $sku,
            'feature' => 'voice_mock', 'kind' => 'pack',
            'price_paise' => $price, 'grant_amount' => $grant, 'active' => true,
        ]);
    }
    $student = voiceReadyStudent($this->tenant, credits: 0);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mocks/voice')
        ->assertStatus(402)
        ->assertJsonPath('error.code', 'no_voice_credits')
        ->assertJsonPath('error.topups.0.sku', 'voice-single')
        ->assertJsonPath('error.topups.0.price_paise', 24_900)
        ->assertJsonPath('error.topups.1.sessions', 3);

    expect(MockInterview::withoutGlobalScopes()->count())->toBe(0);
});

it('refunds the credit and removes the row when the provider cannot create the call', function () {
    $this->voice->shouldFail = true;
    $student = voiceReadyStudent($this->tenant, credits: 1);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mocks/voice')->assertUnprocessable();

    expect(voiceBalance($student))->toBe(1)
        ->and(MockInterview::withoutGlobalScopes()->count())->toBe(0);
});

/* ---- Webhook ---- */

it('rejects an unsigned voice webhook', function () {
    postJson('/api/webhooks/voice', ['message' => ['type' => 'end-of-call-report']])
        ->assertForbidden();

    postJson('/api/webhooks/voice', ['message' => ['type' => 'end-of-call-report']], ['x-vapi-secret' => 'wrong'])
        ->assertForbidden();
});

it('turns the end-of-call report into turns, a scorecard, points, and a cost ledger entry — idempotently', function () {
    $student = voiceReadyStudent($this->tenant, credits: 1);
    Sanctum::actingAs($student);
    postJson('/api/v1/me/mocks/voice')->assertCreated();

    $this->fake->reply = json_encode([
        'overall' => 77,
        'competencies' => [['name' => 'SQL', 'score' => 77]],
        'strong_moments' => ['Clear pipeline story'], 'weak_moments' => [],
        'model_answers' => [], 'actions' => ['a', 'b', 'c'],
    ], JSON_THROW_ON_ERROR);

    voiceWebhook('vapi-session-1', fullCallMessages())->assertOk();

    $interview = MockInterview::withoutGlobalScopes()->sole();
    expect($interview->status)->toBe(MockInterview::STATUS_COMPLETED)
        ->and($interview->mode)->toBe('voice')
        ->and($interview->overall_score)->toBe(77)
        ->and($interview->duration_seconds)->toBe(480)
        ->and($interview->cost_micros)->toBe(420000)
        ->and($interview->turns()->withoutGlobalScopes()->count())->toBe(4); // system line dropped

    $cost = AiEvent::withoutGlobalScopes()->where('model', 'voice-session')->sole();
    expect($cost->cost_micros)->toBe(420000)
        ->and($cost->meta['duration_seconds'])->toBe(480)
        ->and(PointsEvent::withoutGlobalScopes()->where('source', 'mock')->count())->toBe(1);

    // A replayed delivery changes nothing.
    voiceWebhook('vapi-session-1', fullCallMessages())->assertOk();
    expect(MockInterview::withoutGlobalScopes()->count())->toBe(1)
        ->and(AiEvent::withoutGlobalScopes()->where('model', 'voice-session')->count())->toBe(1)
        ->and(PointsEvent::withoutGlobalScopes()->where('source', 'mock')->count())->toBe(1);
});

it('abandons a call with too little candidate signal and refunds the credit', function () {
    $student = voiceReadyStudent($this->tenant, credits: 1);
    Sanctum::actingAs($student);
    postJson('/api/v1/me/mocks/voice')->assertCreated();
    expect(voiceBalance($student))->toBe(0);

    voiceWebhook('vapi-session-1', [
        ['role' => 'assistant', 'message' => 'Tell me about yourself.'],
        ['role' => 'user', 'message' => 'Sorry, I have to go.'],
    ], duration: 35)->assertOk();

    $interview = MockInterview::withoutGlobalScopes()->sole();
    expect($interview->status)->toBe(MockInterview::STATUS_ABANDONED)
        ->and($interview->overall_score)->toBeNull()
        ->and(voiceBalance($student))->toBe(1)
        ->and(PointsEvent::withoutGlobalScopes()->where('source', 'mock')->count())->toBe(0);
});

it('abandons and refunds on a failed call even with a long transcript', function () {
    $student = voiceReadyStudent($this->tenant, credits: 1);
    Sanctum::actingAs($student);
    postJson('/api/v1/me/mocks/voice')->assertCreated();

    voiceWebhook('vapi-session-1', fullCallMessages(), type: 'call.failed')->assertOk();

    expect(MockInterview::withoutGlobalScopes()->sole()->status)->toBe(MockInterview::STATUS_ABANDONED)
        ->and(voiceBalance($student))->toBe(1);
});

/* ---- Summary ---- */

it('surfaces voice credits, the live session, and top-ups in the mock summary', function () {
    config(['monetization.text_practice_enabled' => true]);
    Product::query()->create([
        'tenant_id' => $this->tenant->id, 'sku' => 'voice-single', 'name' => 'Voice mock · single',
        'feature' => 'voice_mock', 'kind' => 'pack', 'price_paise' => 24_900, 'grant_amount' => 1, 'active' => true,
    ]);
    $student = voiceReadyStudent($this->tenant, credits: 3);
    Sanctum::actingAs($student);
    postJson('/api/v1/me/mocks/voice')->assertCreated();

    getJson('/api/v1/me/mocks')
        ->assertOk()
        ->assertJsonPath('data.voice.credits', 2)
        ->assertJsonPath('data.voice.max_minutes', 10)
        ->assertJsonPath('data.voice.in_progress.join_url', 'https://voice.example/join/vapi-session-1')
        ->assertJsonPath('data.voice.topups.0.sku', 'voice-single');
});
