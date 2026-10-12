<?php

declare(strict_types=1);

use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceInvite;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\TaurusFixtures;

beforeEach(function (): void {
    TaurusFixtures::cleanPlatform();
    $this->seed(RolePermissionSeeder::class);
    config(['app.frontend_url' => 'https://app.example.test']);

    $this->tenant = Tenant::factory()->create();
    $this->founder = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'email' => 'Founder@Example.com']);
    $this->founder->assignRole('super-admin');
});

/** @return array<string, mixed> */
function taurusAdmin(string $action, array $options = []): array
{
    Artisan::call('taurus:admin', ['action' => $action, ...$options]);

    return json_decode(trim(Artisan::output()), true);
}

it('creates a client workspace with an owner invite, then lists it', function (): void {
    $out = taurusAdmin('create', ['--actor' => 'founder@example.com', '--name' => 'Acme Clinic', '--email' => 'Owner@Acme.test', '--owner-name' => 'Asha']);

    expect($out['ok'])->toBeTrue()
        ->and($out['workspace']['name'])->toBe('Acme Clinic')
        ->and($out['workspace']['status'])->toBe('active')
        ->and($out['invite_url'])->toStartWith('https://app.example.test/taurusai/claim/');

    $workspace = TaurusWorkspace::withoutGlobalScopes()->findOrFail($out['workspace']['id']);
    $invite = TaurusWorkspaceInvite::withoutGlobalScopes()->where('taurus_workspace_id', $workspace->id)->firstOrFail();
    expect($workspace->tenant_id)->toBe($this->tenant->id)
        ->and($invite->email)->toBe('owner@acme.test')
        ->and($invite->role->value)->toBe('owner')
        ->and($invite->token_hash)->toBe(hash('sha256', substr($out['invite_url'], strrpos($out['invite_url'], '/') + 1)));

    $list = taurusAdmin('list', ['--actor' => 'founder@example.com']);
    expect($list['ok'])->toBeTrue()
        ->and(collect($list['workspaces'])->pluck('name')->all())->toContain('Acme Clinic')
        ->and(collect($list['workspaces'])->firstWhere('name', 'Acme Clinic')['invites'][0])->toMatchArray(['email' => 'owner@acme.test', 'role' => 'owner', 'accepted_at' => null]);
});

it('invites more people and suspends or reactivates a workspace', function (): void {
    $id = taurusAdmin('create', ['--actor' => 'founder@example.com', '--name' => 'Acme', '--email' => 'owner@acme.test'])['workspace']['id'];

    $invite = taurusAdmin('invite', ['--actor' => 'founder@example.com', '--workspace' => (string) $id, '--email' => 'ops@acme.test', '--role' => 'viewer']);
    expect($invite['ok'])->toBeTrue()->and($invite['invite_url'])->toContain('/taurusai/claim/');

    expect(taurusAdmin('status', ['--actor' => 'founder@example.com', '--workspace' => (string) $id, '--status' => 'suspended'])['workspace']['status'])->toBe('suspended')
        ->and(taurusAdmin('status', ['--actor' => 'founder@example.com', '--workspace' => (string) $id, '--status' => 'active'])['workspace']['status'])->toBe('active');

    $bad = taurusAdmin('invite', ['--actor' => 'founder@example.com', '--workspace' => (string) $id, '--email' => 'not-an-email', '--role' => 'viewer']);
    expect($bad['ok'])->toBeFalse()->and($bad['error'])->toContain('email');
});

it('refuses anyone who is not the Taurus owner', function (): void {
    $staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff', 'email' => 'staff@example.com']);
    $staff->assignRole('admin');

    expect(taurusAdmin('create', ['--actor' => 'staff@example.com', '--name' => 'X', '--email' => 'x@x.test'])['ok'])->toBeFalse()
        ->and(taurusAdmin('list', ['--actor' => 'nobody@example.com'])['ok'])->toBeFalse()
        ->and(taurusAdmin('list')['ok'])->toBeFalse();

    config(['taurus.owner_emails' => 'someone-else@example.com']);
    expect(taurusAdmin('list', ['--actor' => 'founder@example.com'])['ok'])->toBeFalse()
        ->and(TaurusWorkspace::withoutGlobalScopes()->count())->toBe(0);
});

it('never reaches another tenant\'s workspace', function (): void {
    $other = Tenant::factory()->create();
    $foreign = TaurusWorkspace::withoutGlobalScopes()->create([
        'tenant_id' => $other->id, 'name' => 'Other Co', 'slug' => 'other-co', 'status' => 'active', 'is_owner' => false,
    ]);

    $invite = taurusAdmin('invite', ['--actor' => 'founder@example.com', '--workspace' => (string) $foreign->id, '--email' => 'a@b.test', '--role' => 'owner']);
    $status = taurusAdmin('status', ['--actor' => 'founder@example.com', '--workspace' => (string) $foreign->id, '--status' => 'suspended']);
    $list = taurusAdmin('list', ['--actor' => 'founder@example.com']);

    expect($invite['ok'])->toBeFalse()
        ->and($status['ok'])->toBeFalse()
        ->and($foreign->fresh()->status)->toBe('active')
        ->and(collect($list['workspaces'])->pluck('name')->all())->not->toContain('Other Co');
});
