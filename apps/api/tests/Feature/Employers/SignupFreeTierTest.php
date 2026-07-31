<?php

declare(strict_types=1);

use App\Actions\Auth\GrantSignupCredits;
use App\Enums\EntitlementFeature;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

it('starts a new job seeker on the free tier', function (): void {
    config(['monetization.signup.free_mocks' => 2, 'monetization.signup.free_cvs' => 1]);

    $seeker = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    app(GrantSignupCredits::class)->handle($seeker);

    $entitlements = app(EntitlementService::class);

    // Without this a job seeker could apply but never sit the mock the
    // application depends on, which is a dead end rather than a funnel.
    expect($entitlements->balance($seeker, EntitlementFeature::VoiceMock->value))->toBe(2)
        ->and($entitlements->balance($seeker, EntitlementFeature::Cv->value))->toBe(1);
});

it('never double-grants the free tier', function (): void {
    config(['monetization.signup.free_mocks' => 2, 'monetization.signup.free_cvs' => 1]);

    $seeker = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    app(GrantSignupCredits::class)->handle($seeker);
    app(GrantSignupCredits::class)->handle($seeker);
    app(GrantSignupCredits::class)->handle($seeker);

    expect(app(EntitlementService::class)->balance($seeker, EntitlementFeature::VoiceMock->value))
        ->toBe(2);
});

it('grants nothing when the free tier is switched off', function (): void {
    config(['monetization.signup.free_mocks' => 0, 'monetization.signup.free_cvs' => 0]);

    $seeker = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    app(GrantSignupCredits::class)->handle($seeker);

    expect(app(EntitlementService::class)->balance($seeker, EntitlementFeature::VoiceMock->value))
        ->toBe(0);
});

it('bootstraps an employer owner from the console, creating account and workspace', function (): void {
    $this->artisan('employer:grant-owner', ['email' => 'founder@example.com'])
        ->expectsQuestion('Password for the employer portal (input hidden)', 'a-very-long-password')
        ->expectsQuestion('Confirm password', 'a-very-long-password')
        ->assertSuccessful();

    $user = User::withoutGlobalScopes()->where('email', 'founder@example.com')->firstOrFail();

    expect($user->user_type)->toBe('employer');

    $membership = EmployerMember::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
    expect($membership->role->value)->toBe('owner');

    expect(EmployerWorkspace::withoutGlobalScopes()->count())->toBe(1);
});

it('makes an existing user an owner of the existing workspace without creating another', function (): void {
    $workspace = EmployerWorkspace::factory()->for($this->tenant)->create(['name' => 'Acme']);
    $existing = User::factory()->for($this->tenant)->create([
        'email' => 'founder@example.com',
        'user_type' => 'student',
    ]);

    $this->artisan('employer:grant-owner', ['email' => 'founder@example.com'])
        ->expectsQuestion('Password for the employer portal (input hidden)', 'a-very-long-password')
        ->expectsQuestion('Confirm password', 'a-very-long-password')
        ->assertSuccessful();

    expect(EmployerWorkspace::withoutGlobalScopes()->count())->toBe(1)
        ->and($existing->fresh()->user_type)->toBe('employer');

    expect(EmployerMember::withoutGlobalScopes()
        ->where('employer_workspace_id', $workspace->id)
        ->where('user_id', $existing->id)
        ->exists())->toBeTrue();
});

it('refuses a short password rather than creating a weak owner', function (): void {
    $this->artisan('employer:grant-owner', ['email' => 'founder@example.com'])
        ->expectsQuestion('Password for the employer portal (input hidden)', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();

    expect(User::withoutGlobalScopes()->where('email', 'founder@example.com')->exists())->toBeFalse();
});

it('refuses when the confirmation does not match', function (): void {
    $this->artisan('employer:grant-owner', ['email' => 'founder@example.com'])
        ->expectsQuestion('Password for the employer portal (input hidden)', 'a-very-long-password')
        ->expectsQuestion('Confirm password', 'a-different-password')
        ->assertFailed();

    expect(User::withoutGlobalScopes()->where('email', 'founder@example.com')->exists())->toBeFalse();
});

it('names the reason an employer sign-in is refused', function (): void {
    // A row inserted by hand: plain-text password, wrong type, deactivated.
    // Written straight to the table — the model's `hashed` cast would
    // otherwise hash it and hide the very case being reproduced.
    $user = User::factory()->for($this->tenant)->create([
        'email' => 'manual@example.com',
        'user_type' => 'student',
        'is_active' => false,
    ]);
    DB::table('users')->where('id', $user->id)->update(['password' => 'plaintext-not-a-hash']);

    $this->artisan('employer:diagnose', ['email' => 'manual@example.com'])
        ->expectsOutputToContain('Password is not a hash')
        ->expectsOutputToContain('no workspace membership')
        ->expectsOutputToContain('deactivated')
        ->assertFailed();
});

it('reports a healthy employer account as sound', function (): void {
    $this->artisan('employer:grant-owner', ['email' => 'ok@example.com'])
        ->expectsQuestion('Password for the employer portal (input hidden)', 'a-very-long-password')
        ->expectsQuestion('Confirm password', 'a-very-long-password')
        ->assertSuccessful();

    $this->artisan('employer:diagnose', ['email' => 'ok@example.com'])->assertSuccessful();
});
