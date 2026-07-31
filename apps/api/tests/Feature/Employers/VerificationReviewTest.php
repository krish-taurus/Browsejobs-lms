<?php

declare(strict_types=1);

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\InAppNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Verification\VerificationGateway;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
    $this->candidate = User::factory()->for($this->tenant)->create([
        'user_type' => 'student',
        'name' => 'Meera Iyer',
    ]);

    $this->reviewer = reviewerIn($this->tenant);
});

/** Staff who may work the verification queue. */
function reviewerIn(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

/** Staff without manage-placements — the queue must stay shut to them. */
function nonReviewerIn(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('counselor');

    return $user;
}

function submitted(User $candidate, VerificationKind $kind): CandidateVerificationCheck
{
    return app(VerificationGateway::class)->submit($candidate, $kind, ['reference' => 'REF-1']);
}

it('queues pending checks with the candidate named and the wait visible', function (): void {
    submitted($this->candidate, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    $response = $this->getJson('/api/v1/admin/verifications')->assertOk();

    $response->assertJsonPath('data.0.kind', 'identity')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.candidate.name', 'Meera Iyer')
        ->assertJsonPath('meta.pending', 1);

    expect($response->json('data.0'))->toHaveKey('waiting_days');
});

it('verifies a check and tells the candidate', function (): void {
    $check = submitted($this->candidate, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/admin/verifications/{$check->id}/settle", [
        'decision' => 'verified',
        'evidence' => ['issuer' => 'DigiLocker'],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');

    expect($check->fresh()->reviewed_by_id)->toBe($this->reviewer->id)
        ->and($check->fresh()->expires_at)->not->toBeNull();

    expect(InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->exists())->toBeTrue();
});

it('announces the badge only once the last required check lands', function (): void {
    $identity = submitted($this->candidate, VerificationKind::Identity);
    $employment = submitted($this->candidate, VerificationKind::Employment);

    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/admin/verifications/{$identity->id}/settle", ['decision' => 'verified'])
        ->assertOk();

    $titles = fn (): array => InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->pluck('title')
        ->all();

    expect($titles())->not->toContain('Your profile is verified');

    $this->postJson("/api/v1/admin/verifications/{$employment->id}/settle", ['decision' => 'verified'])
        ->assertOk();

    expect($titles())->toContain('Your profile is verified');
});

it('requires a reason to fail a check', function (): void {
    $check = submitted($this->candidate, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    // A rejection a candidate cannot act on just produces the same
    // resubmission and another wait.
    $this->postJson("/api/v1/admin/verifications/{$check->id}/settle", ['decision' => 'failed'])
        ->assertStatus(422);

    $this->postJson("/api/v1/admin/verifications/{$check->id}/settle", [
        'decision' => 'failed',
        'reason' => 'The uploaded ID was unreadable.',
    ])->assertOk();

    expect(InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->value('body'))->toBe('The uploaded ID was unreadable.');
});

it('rejects a decision that is neither verified nor failed', function (): void {
    $check = submitted($this->candidate, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/admin/verifications/{$check->id}/settle", ['decision' => 'pending'])
        ->assertStatus(422);
});

it('denies a staff member without the placements permission', function (): void {
    submitted($this->candidate, VerificationKind::Identity);

    Sanctum::actingAs(nonReviewerIn($this->tenant));

    $this->getJson('/api/v1/admin/verifications')->assertForbidden();
});

it('denies a candidate reaching the review queue', function (): void {
    Sanctum::actingAs($this->candidate);

    $this->getJson('/api/v1/admin/verifications')->assertForbidden();
});

it('never shows another tenant submissions', function (): void {
    submitted($this->candidate, VerificationKind::Identity);

    $otherTenant = Tenant::factory()->create();
    $stranger = User::factory()->for($otherTenant)->create(['user_type' => 'student']);
    submitted($stranger, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    $response = $this->getJson('/api/v1/admin/verifications')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.candidate.id'))->toBe($this->candidate->id);
});

it('cannot settle a check belonging to another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $stranger = User::factory()->for($otherTenant)->create(['user_type' => 'student']);
    $foreign = submitted($stranger, VerificationKind::Identity);

    Sanctum::actingAs($this->reviewer);

    $this->postJson("/api/v1/admin/verifications/{$foreign->id}/settle", ['decision' => 'verified'])
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe(VerificationStatus::Pending);
});
