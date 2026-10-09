<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\EmployerWorkspace;
use App\Models\Permission;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceInvite;
use App\Models\TaurusWorkspaceMember;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TaurusFixtures;

beforeEach(function (): void {
    TaurusFixtures::cleanPlatform();
    $this->seed(RolePermissionSeeder::class);

    config(['sanctum.stateful' => ['acme-tau.test'], 'app.frontend_url' => 'https://app.example.test']);
    $this->tenant = Tenant::factory()->domain('acme-tau.test')->create();
    $this->founder = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'email' => 'Founder@Example.com']);
    $this->founder->assignRole('super-admin');
});

/** Pull the raw token out of an invite URL. */
function inviteToken(string $url): string
{
    return substr($url, strrpos($url, '/') + 1);
}

it('is owner-only: an admin granted manage-settings is still refused', function (): void {
    $permission = Permission::query()->firstOrCreate(['slug' => 'manage-settings'], ['name' => 'Manage settings']);
    Role::query()->where('slug', 'admin')->sole()->permissions()->syncWithoutDetaching([$permission->id]);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    expect($admin->hasPermission('manage-settings'))->toBeTrue();

    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/admin/taurus/workspaces')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    $this->getJson('/api/v1/admin/taurus/platform')->assertForbidden();
    $this->putJson('/api/v1/admin/taurus/platform', ['brain_provider' => 'openai'])->assertForbidden();
    $this->postJson('/api/v1/admin/taurus/hq')->assertForbidden();

    expect(PlatformSetting::query()->where('group', 'taurus')->exists())->toBeFalse()
        ->and(TaurusWorkspace::withoutGlobalScopes()->count())->toBe(0);
});

it('honours the owner email allow-list, case-insensitively', function (): void {
    Sanctum::actingAs($this->founder);

    config(['taurus.owner_emails' => 'someone-else@example.com']);
    $this->getJson('/api/v1/admin/taurus/workspaces')->assertForbidden();
    $this->getJson('/api/v1/admin/taurus/platform')->assertForbidden();

    config(['taurus.owner_emails' => 'someone-else@example.com, founder@example.com']);
    $this->getJson('/api/v1/admin/taurus/workspaces')->assertOk();
});

it('creates HQ once, with the founder as owner', function (): void {
    Sanctum::actingAs($this->founder);

    $first = $this->postJson('/api/v1/admin/taurus/hq')->assertOk()->json('data');
    $second = $this->postJson('/api/v1/admin/taurus/hq')->assertOk()->json('data');

    expect($first['id'])->toBe($second['id'])
        ->and($first)->toMatchArray(['name' => 'Taurus HQ', 'is_owner' => true, 'status' => 'active', 'role' => 'owner', 'members_count' => 1])
        ->and(TaurusWorkspace::withoutGlobalScopes()->where('is_owner', true)->count())->toBe(1)
        ->and(TaurusWorkspaceMember::withoutGlobalScopes()->where('user_id', $this->founder->id)->count())->toBe(1);

    $this->getJson('/api/v1/taurus/workspaces')->assertOk()
        ->assertJsonPath('data.0.id', $first['id'])
        ->assertJsonPath('data.0.role', 'owner');
});

it('creates a client workspace, and the client claims it, signs in and sees only their own', function (): void {
    Sanctum::actingAs($this->founder);
    $this->postJson('/api/v1/admin/taurus/hq')->assertOk();

    $created = $this->postJson('/api/v1/admin/taurus/workspaces', [
        'name' => 'Acme Logistics',
        'owner_email' => 'ceo@acme.example',
        'owner_name' => 'Ravi',
    ])->assertCreated()->json('data');

    expect($created['workspace'])->toMatchArray(['name' => 'Acme Logistics', 'slug' => 'acme-logistics', 'is_owner' => false, 'members_count' => 0, 'agents_count' => 0])
        ->and($created['invite_url'])->toStartWith('https://app.example.test/taurusai/claim/')
        ->and(AuditLog::query()->where('action', 'taurus.workspace.created')->count())->toBe(2);

    $token = inviteToken($created['invite_url']);
    expect(TaurusWorkspaceInvite::withoutGlobalScopes()->sole()->token_hash)->toBe(hash('sha256', $token));

    // The client claims from the browser, signed out.
    app('auth')->forgetGuards();
    $this->withHeader('Origin', 'http://acme-tau.test')
        ->postJson('http://acme-tau.test/api/v1/auth/taurus/claim', [
            'token' => $token,
            'name' => 'Ravi Kumar',
            'password' => 'a-strong-pass-1',
            'password_confirmation' => 'a-strong-pass-1',
        ])->assertOk()
        ->assertJsonPath('status', 'authenticated')
        ->assertJsonPath('user.user_type', 'client')
        ->assertJsonPath('workspace.id', $created['workspace']['id'])
        ->assertJsonPath('workspace.role', 'owner');

    $client = User::query()->withoutGlobalScopes()->where('email', 'ceo@acme.example')->sole();
    $this->assertAuthenticatedAs($client, 'web');

    // A used link cannot be claimed again.
    app('auth')->forgetGuards();
    $this->withHeader('Origin', 'http://acme-tau.test')
        ->postJson('http://acme-tau.test/api/v1/auth/taurus/claim', [
            'token' => $token, 'name' => 'X', 'password' => 'another-pass-22', 'password_confirmation' => 'another-pass-22',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

    // Sign in later with email + password.
    app('auth')->forgetGuards();
    $this->withHeader('Origin', 'http://acme-tau.test')
        ->postJson('http://acme-tau.test/api/v1/auth/taurus/login', ['email' => 'ceo@acme.example', 'password' => 'a-strong-pass-1'])
        ->assertOk()
        ->assertJsonPath('status', 'authenticated');

    Sanctum::actingAs($client);
    $this->getJson('/api/v1/taurus/workspaces')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Acme Logistics');

    // HQ is invisible to the client.
    $hq = TaurusWorkspace::withoutGlobalScopes()->where('is_owner', true)->sole();
    $this->getJson("/api/v1/taurus/workspaces/{$hq->id}/state")->assertNotFound();
    $this->getJson('/api/v1/admin/taurus/workspaces')->assertForbidden();
});

it('refuses Taurus sign-in to anyone without an active workspace', function (): void {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'password' => Hash::make('student-pass-11')]);
    $suspendedMember = User::factory()->for($this->tenant)->create(['user_type' => 'client', 'password' => Hash::make('client-pass-111')]);
    $ws = TaurusFixtures::workspace($this->tenant, ['status' => 'suspended']);
    TaurusFixtures::member($ws, $suspendedMember, 'owner');

    foreach ([[$student->email, 'student-pass-11'], [$suspendedMember->email, 'client-pass-111'], ['nobody@example.com', 'whatever-123']] as [$email, $password]) {
        $this->withHeader('Origin', 'http://acme-tau.test')
            ->postJson('http://acme-tau.test/api/v1/auth/taurus/login', ['email' => $email, 'password' => $password])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', "Those details don't match a Taurus workspace.");
    }

    $this->assertGuest('web');
});

it('only lets an existing account join with its own password', function (): void {
    $existing = User::factory()->for($this->tenant)->create(['email' => 'ops@acme.example', 'password' => Hash::make('right-password-1')]);
    $ws = TaurusFixtures::workspace($this->tenant);

    Sanctum::actingAs($this->founder);
    $url = $this->postJson("/api/v1/admin/taurus/workspaces/{$ws->id}/invites", ['email' => 'ops@acme.example', 'role' => 'viewer'])
        ->assertCreated()->json('data.invite_url');
    app('auth')->forgetGuards();

    $claim = fn (string $password) => $this->withHeader('Origin', 'http://acme-tau.test')
        ->postJson('http://acme-tau.test/api/v1/auth/taurus/claim', [
            'token' => inviteToken($url), 'name' => 'Ops', 'password' => $password, 'password_confirmation' => $password,
        ]);

    $claim('wrong-password-1')->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(TaurusWorkspaceMember::withoutGlobalScopes()->where('user_id', $existing->id)->exists())->toBeFalse();

    $claim('right-password-1')->assertOk()->assertJsonPath('workspace.role', 'viewer');
    expect(TaurusWorkspaceMember::withoutGlobalScopes()->where('user_id', $existing->id)->sole()->role->value)->toBe('viewer')
        ->and(User::query()->withoutGlobalScopes()->where('email', 'ops@acme.example')->count())->toBe(1);
});

it('suspends a client workspace but never HQ', function (): void {
    Sanctum::actingAs($this->founder);
    $hq = $this->postJson('/api/v1/admin/taurus/hq')->json('data');
    $client = TaurusFixtures::workspace($this->tenant, ['name' => 'Acme']);

    $this->patchJson("/api/v1/admin/taurus/workspaces/{$client->id}", ['status' => 'suspended', 'name' => 'Acme Ltd'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.name', 'Acme Ltd');

    $this->patchJson("/api/v1/admin/taurus/workspaces/{$hq['id']}", ['status' => 'suspended'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'cannot_suspend_hq');

    $foreign = TaurusFixtures::workspace(Tenant::factory()->create());
    $this->patchJson("/api/v1/admin/taurus/workspaces/{$foreign->id}", ['status' => 'suspended'])->assertNotFound();
});

it('lists workspaces with counts but no agent data or keys', function (): void {
    $client = TaurusFixtures::workspace($this->tenant, ['name' => 'Acme']);
    TaurusFixtures::credential($client, 'openai', 'sk-CLIENT-secret-9999');
    $token = TaurusFixtures::token($client);
    TaurusFixtures::agent($client, 'secret-bot');
    TaurusFixtures::workspace(Tenant::factory()->create(), ['name' => 'Other tenant']);

    Sanctum::actingAs($this->founder);
    $response = $this->getJson('/api/v1/admin/taurus/workspaces')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'name', 'slug', 'status', 'is_owner', 'members_count', 'agents_count', 'last_activity_at', 'created_at'])
        ->and($response->json('data.0.agents_count'))->toBe(1)
        ->and($response->json('data.0.last_activity_at'))->not->toBeNull();

    foreach (['sk-CLIENT-secret-9999', $token, 'secret-bot', hash('sha256', $token)] as $hidden) {
        expect($response->getContent())->not->toContain($hidden);
    }
});

it('manages the platform brain used by the employer hiring floor', function (): void {
    Http::preventStrayRequests();
    Sanctum::actingAs($this->founder);

    $this->putJson('/api/v1/admin/taurus/platform', [
        'brain_provider' => 'gemini',
        'providers' => ['gemini' => ['api_key' => 'PLATFORM-gemini-secret-1212']],
    ])->assertOk()
        ->assertJsonPath('data.brain.provider', 'gemini')
        ->assertJsonPath('data.brain.active.provider', 'gemini');

    $body = $this->getJson('/api/v1/admin/taurus/platform')->assertOk();
    expect($body->getContent())->not->toContain('PLATFORM-gemini-secret-1212')
        ->and(array_keys($body->json('data')))->toBe(['brain', 'providers', 'voice']);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'OK']]], 'finishReason' => 'STOP']],
    ])]);
    $this->postJson('/api/v1/admin/taurus/platform/test', ['target' => 'gemini'])->assertOk()->assertJsonPath('data.ok', true);
});

it('keeps client users out of staff and employer surfaces', function (): void {
    $client = User::factory()->for($this->tenant)->create(['user_type' => 'client']);
    TaurusFixtures::member(TaurusFixtures::workspace($this->tenant), $client, 'owner');
    $employerWorkspace = EmployerWorkspace::factory()->for($this->tenant)->create();

    Sanctum::actingAs($client);
    $this->getJson('/api/v1/admin/settings')->assertForbidden();
    $this->getJson("/api/v1/employer/workspaces/{$employerWorkspace->id}/dashboard")->assertForbidden();
    $this->getJson("/api/v1/employer/workspaces/{$employerWorkspace->id}/hiring-floor")->assertForbidden();
    $this->getJson('/api/v1/admin/taurus/workspaces')->assertForbidden();

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user_type', 'client');
});
