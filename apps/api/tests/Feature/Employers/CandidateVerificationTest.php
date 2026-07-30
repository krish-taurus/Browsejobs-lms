<?php

declare(strict_types=1);

use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\CandidateVerificationCheck;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Verification\VerificationGateway;
use App\Support\Verification\VerificationProfile;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->candidate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('lists every check as not started before the candidate does anything', function (): void {
    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('/api/v1/me/verification')->assertOk();

    expect($response->json('data.checks'))->toHaveCount(count(VerificationKind::cases()))
        ->and($response->json('data.badge'))->toBeFalse()
        ->and($response->json('data.verified_count'))->toBe(0);

    // Every check names what it is for, so the ask is never arbitrary.
    expect($response->json('data.checks.0.employer_value'))->not->toBeEmpty();
});

it('submits a check as pending and never self-verifies', function (): void {
    Sanctum::actingAs($this->candidate);

    $this->postJson('/api/v1/me/verification/identity', ['reference' => 'DL-123'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    // The shipped provider is manual review — a candidate cannot mark
    // themselves verified, which is the whole value of the badge.
    expect(CandidateVerificationCheck::withoutGlobalScopes()
        ->where('user_id', $this->candidate->id)
        ->where('kind', 'identity')
        ->first()->status)->toBe(VerificationStatus::Pending);
});

it('rejects an unknown check kind', function (): void {
    Sanctum::actingAs($this->candidate);

    $this->postJson('/api/v1/me/verification/astrology')->assertNotFound();
});

it('awards the badge only when every required check is verified and live', function (): void {
    $gateway = app(VerificationGateway::class);
    $profile = new VerificationProfile($this->candidate);

    $identity = $gateway->submit($this->candidate, VerificationKind::Identity);
    $gateway->settle($identity, VerificationStatus::Verified);

    expect($profile->hasBadge())->toBeFalse(); // employment still missing

    $employment = $gateway->submit($this->candidate, VerificationKind::Employment);
    $gateway->settle($employment, VerificationStatus::Verified);

    expect($profile->hasBadge())->toBeTrue();

    // An expired check stops counting without any sweep job running.
    $employment->update(['expires_at' => now()->subDay()]);
    expect($profile->hasBadge())->toBeFalse()
        ->and($employment->fresh()->effectiveStatus())->toBe(VerificationStatus::Expired);
});

it('audits a settled check because the badge is a claim made to employers', function (): void {
    $gateway = app(VerificationGateway::class);
    $reviewer = User::factory()->for($this->tenant)->create();

    $check = $gateway->submit($this->candidate, VerificationKind::Identity);
    $gateway->settle($check, VerificationStatus::Verified, $reviewer);

    expect(AuditLog::withoutGlobalScopes()
        ->where('action', 'verification.settled')
        ->where('actor_id', $reviewer->id)
        ->exists())->toBeTrue();
});

it('does not re-run a provider for a check already verified and live', function (): void {
    $gateway = app(VerificationGateway::class);

    $check = $gateway->submit($this->candidate, VerificationKind::Identity);
    $gateway->settle($check, VerificationStatus::Verified);
    $verifiedAt = $check->fresh()->verified_at;

    $again = $gateway->submit($this->candidate, VerificationKind::Identity);

    expect($again->status)->toBe(VerificationStatus::Verified)
        ->and($again->verified_at->toIso8601String())->toBe($verifiedAt->toIso8601String());
});

it('reports no uplift figure when the sample is too thin to be honest', function (): void {
    Sanctum::actingAs($this->candidate);

    // With a handful of applications there is nothing truthful to say, so the
    // API returns null rather than a placeholder percentage.
    $this->getJson('/api/v1/me/verification')
        ->assertOk()
        ->assertJsonPath('data.observed_uplift', null);
});

it('never leaks another tenant verification state', function (): void {
    $otherTenant = Tenant::factory()->create();
    $stranger = User::factory()->for($otherTenant)->create();

    app(VerificationGateway::class)->submit($stranger, VerificationKind::Identity);

    Sanctum::actingAs($this->candidate);

    $response = $this->getJson('/api/v1/me/verification')->assertOk();

    // The candidate sees only their own (all not-started) checks.
    expect(collect($response->json('data.checks'))->pluck('status')->unique()->all())
        ->toBe(['not_started']);
});
