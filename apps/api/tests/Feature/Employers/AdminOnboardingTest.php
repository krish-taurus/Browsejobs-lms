<?php

declare(strict_types=1);

use App\Actions\Employers\OnboardEmployer;
use App\Models\AuditLog;
use App\Models\EmployerInvite;
use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * PRD-E F1 — onboarding an employer from the admin panel, and the employer
 * claiming their own credential from the invite.
 *
 * The assertion that matters most is the last one: a token must never be
 * able to overwrite the password of an account that already had one.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    // A resolvable host: the claim endpoint is public and tenant-scoped by
    // domain, and Sanctum only attaches a session to a stateful origin — so
    // the host has to be declared stateful, not just sent as an Origin.
    config(['sanctum.stateful' => ['acme-emp.test']]);
    $this->tenant = Tenant::query()->where('slug', 'browsejobs')->first()
        ?? Tenant::factory()->create(['slug' => 'browsejobs']);
    $this->tenant->forceFill(['domain' => 'acme-emp.test'])->save();
    $this->claimUrl = 'http://acme-emp.test/api/v1/employer/invites/claim';

    $this->super = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->super->assignRole('super-admin');
});

it('creates the workspace, the owner and a single-use invite', function (): void {
    Sanctum::actingAs($this->super);

    $response = $this->postJson('/api/v1/admin/employers', [
        'company' => 'Northwind Retail',
        'owner_email' => 'ops@northwind.test',
        'owner_name' => 'Priya Rao',
        'industry' => 'Retail',
    ])->assertCreated();

    $workspace = EmployerWorkspace::withoutGlobalScopes()->where('name', 'Northwind Retail')->firstOrFail();
    $owner = User::withoutGlobalScopes()->where('email', 'ops@northwind.test')->firstOrFail();

    expect($owner->user_type)->toBe('employer')
        ->and(EmployerMember::withoutGlobalScopes()
            ->where('employer_workspace_id', $workspace->id)
            ->where('user_id', $owner->id)
            ->value('role')?->value)->toBe('owner');

    // The claim link is returned exactly once, at creation.
    expect($response->json('data.invite.claim_url'))->toContain('/employer/claim/');
});

it('never lets an admin choose the employer password', function (): void {
    Sanctum::actingAs($this->super);

    // There is no field for it, and sending one changes nothing: staff must
    // not knowingly hold a customer's login.
    $this->postJson('/api/v1/admin/employers', [
        'company' => 'Acme Labs',
        'owner_email' => 'founder@acme.test',
        'password' => 'chosen-by-an-admin',
    ])->assertCreated();

    $owner = User::withoutGlobalScopes()->where('email', 'founder@acme.test')->firstOrFail();

    expect(Hash::check('chosen-by-an-admin', $owner->password))->toBeFalse();
});

it('does not return the invite token in the listing', function (): void {
    Sanctum::actingAs($this->super);

    $this->postJson('/api/v1/admin/employers', [
        'company' => 'Zeta Systems', 'owner_email' => 'hr@zeta.test',
    ])->assertCreated();

    $body = $this->getJson('/api/v1/admin/employers')->assertOk();
    $token = EmployerInvite::withoutGlobalScopes()->latest('id')->value('token');

    // Ops can see that an invite is outstanding, not replay it.
    expect($body->json('data.0.pending_invite.email'))->toBe('hr@zeta.test')
        ->and($body->getContent())->not->toContain($token);
});

it('lets the employer set their own password from the invite and signs them in', function (): void {
    $result = app(OnboardEmployer::class)->handle($this->tenant, [
        'company' => 'Acme Technologies',
        'owner_email' => 'founder@acme.test',
    ], $this->super);

    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson($this->claimUrl, [
            'token' => $result['invite']->token,
            'password' => 'a-password-they-chose',
            'password_confirmation' => 'a-password-they-chose',
        ])->assertCreated();

    $owner = $result['owner']->fresh();

    expect(Hash::check('a-password-they-chose', $owner->password))->toBeTrue()
        ->and($this->isAuthenticated())->toBeTrue();

    // Single use: the same link cannot be replayed.
    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson($this->claimUrl, [
            'token' => $result['invite']->token,
            'password' => 'a-different-password',
            'password_confirmation' => 'a-different-password',
        ])->assertStatus(422);
});

it('refuses a weak first password on an account that can see every applicant', function (): void {
    $result = app(OnboardEmployer::class)->handle($this->tenant, [
        'company' => 'Acme Technologies', 'owner_email' => 'founder@acme.test',
    ], $this->super);

    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson($this->claimUrl, [
            'token' => $result['invite']->token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('never lets an invite overwrite the password of an account that already had one', function (): void {
    // An existing user — a candidate, say — whose employer signs up with the
    // same email. Onboarding reuses the account, but the invite must not be
    // able to take it over.
    $existing = User::factory()->for($this->tenant)->create([
        'email' => 'someone@real.test',
        'user_type' => 'student',
        'password' => 'their-own-real-password',
    ]);

    $result = app(OnboardEmployer::class)->handle($this->tenant, [
        'company' => 'Some Company', 'owner_email' => 'someone@real.test',
    ], $this->super);

    expect($result['invite']->grants_credential)->toBeFalse();

    $this->withHeader('Origin', 'http://acme-emp.test')
        ->postJson($this->claimUrl, [
            'token' => $result['invite']->token,
            'password' => 'an-attacker-chosen-password',
            'password_confirmation' => 'an-attacker-chosen-password',
        ])->assertStatus(422);

    expect(Hash::check('their-own-real-password', $existing->fresh()->password))->toBeTrue();
});

it('suspends and restores portal access, and audits both', function (): void {
    Sanctum::actingAs($this->super);

    $this->postJson('/api/v1/admin/employers', [
        'company' => 'Acme Technologies', 'owner_email' => 'founder@acme.test',
    ])->assertCreated();

    $workspace = EmployerWorkspace::withoutGlobalScopes()->firstOrFail();

    $this->postJson("/api/v1/admin/employers/{$workspace->id}/status", ['status' => 'suspended'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');

    $this->postJson("/api/v1/admin/employers/{$workspace->id}/status", ['status' => 'active'])
        ->assertOk()->assertJsonPath('data.status', 'active');

    expect(AuditLog::query()->where('action', 'employer.access_changed')->count())->toBe(2);
});

it('denies a plain admin — onboarding a customer is a platform action', function (): void {
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/employers')->assertForbidden();
    $this->postJson('/api/v1/admin/employers', [
        'company' => 'Sneaky Ltd', 'owner_email' => 'x@y.test',
    ])->assertForbidden();

    expect(EmployerWorkspace::withoutGlobalScopes()->count())->toBe(0);
});

it('denies a student outright', function (): void {
    Sanctum::actingAs(User::factory()->for($this->tenant)->create(['user_type' => 'student']));

    $this->getJson('/api/v1/admin/employers')->assertForbidden();
});

it('actually cuts portal access when a workspace is suspended', function (): void {
    $result = app(OnboardEmployer::class)->handle($this->tenant, [
        'company' => 'Acme Technologies', 'owner_email' => 'founder@acme.test',
    ], $this->super);

    $workspace = $result['workspace'];
    $owner = $result['owner'];

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/employer/workspaces/{$workspace->id}/dashboard")->assertOk();

    // Suspension used to be a column nothing read, so the button changed a
    // label while the team kept full access to candidate contact details.
    $workspace->forceFill(['status' => 'suspended'])->save();

    $this->getJson("/api/v1/employer/workspaces/{$workspace->id}/dashboard")->assertForbidden();
    $this->getJson("/api/v1/employer/workspaces/{$workspace->id}/jobs")->assertForbidden();

    $workspace->forceFill(['status' => 'active'])->save();
    $this->getJson("/api/v1/employer/workspaces/{$workspace->id}/dashboard")->assertOk();
});
