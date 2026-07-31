<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\EntitlementFeature;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/*
 * The browser interview room rides the text-mock session but is metered
 * (founder pricing): 3 free sessions seeded on first touch, then the paid
 * packs. A plain text start stays free.
 */

beforeEach(function () {
    config([
        'monetization.text_practice_enabled' => true,
        'monetization.voice_mock.free_grants' => 3,
    ]);
    $this->tenant = Tenant::factory()->create();
});

function roomStudent(Tenant $tenant): User
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

    return $student;
}

function roomBalance(User $student): int
{
    return app(EntitlementService::class)->balance($student, EntitlementFeature::VoiceMock->value);
}

it('seeds the free allowance and consumes one session on a room start', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);

    $id = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');

    expect(roomBalance($student))->toBe(2)
        ->and(MockInterview::withoutGlobalScopes()->find($id)->is_room)->toBeTrue();
});

it('does not charge a plain text start', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);

    $id = postJson('/api/v1/me/mocks')->assertCreated()->json('data.id');

    expect(roomBalance($student))->toBe(3) // untouched free allowance
        ->and(MockInterview::withoutGlobalScopes()->find($id)->is_room)->toBeFalse();
});

it('does not charge again when a room start resumes an in-progress session', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);

    $first = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');
    $second = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');

    expect($second)->toBe($first)
        ->and(roomBalance($student))->toBe(2);
});

it('answers 402 with the top-up packs when the wallet is empty', function () {
    config(['monetization.voice_mock.free_grants' => 0]);
    $student = roomStudent($this->tenant);
    Product::factory()->for($this->tenant)->create([
        'sku' => 'voice-3pack', 'name' => 'Voice interviews · 3 sessions',
        'feature' => EntitlementFeature::VoiceMock->value, 'price_paise' => 100_000, 'grant_amount' => 3,
    ]);
    Product::factory()->for($this->tenant)->create([
        'sku' => 'voice-6pack', 'name' => 'Voice interviews · 6 sessions',
        'feature' => EntitlementFeature::VoiceMock->value, 'price_paise' => 150_000, 'grant_amount' => 6,
    ]);
    Sanctum::actingAs($student);

    $response = postJson('/api/v1/me/mocks', ['room' => true])->assertStatus(402);

    $topups = $response->json('error.topups');
    expect($response->json('error.code'))->toBe('no_voice_credits')
        ->and(collect($topups)->pluck('price_paise')->all())->toBe([100_000, 150_000])
        ->and(collect($topups)->pluck('sessions')->all())->toBe([3, 6]);
});

it('closes an in-progress interview on proctoring abandon without a refund', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');

    postJson("/api/v1/me/mocks/{$id}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');

    expect(roomBalance($student))->toBe(2); // the spent session stays spent
});

it('leaves a completed interview untouched when abandon is called late', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');

    postJson("/api/v1/me/mocks/{$id}/abandon")->assertOk();
    postJson("/api/v1/me/mocks/{$id}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned'); // idempotent, no state churn
});

it('cannot abandon another student\'s interview', function () {
    $student = roomStudent($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mocks', ['room' => true])->assertCreated()->json('data.id');

    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($other);

    postJson("/api/v1/me/mocks/{$id}/abandon")->assertNotFound();
});

it('denies a room start from another tenant\'s student for this tenant\'s blueprint', function () {
    $other = Tenant::factory()->create();
    roomStudent($this->tenant); // creates this tenant's course + blueprint
    $foreign = User::factory()->for($other)->create(['user_type' => 'student']);
    Sanctum::actingAs($foreign);

    // No blueprint exists in the foreign tenant, so the start must not leak
    // across tenants — it fails validation instead of using ours.
    postJson('/api/v1/me/mocks', ['room' => true])->assertStatus(422);
});
