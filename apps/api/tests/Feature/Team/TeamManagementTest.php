<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
});

/** An admin (has manage-users) acting within the tenant. */
function admin(Tenant $tenant): User
{
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff', 'is_active' => true]);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    return $admin;
}

it('lists staff and the assignable roles', function () {
    admin($this->tenant);

    getJson('/api/v1/admin/team')->assertOk()
        ->assertJsonPath('data.0.roles.0', 'admin')
        ->assertJsonFragment(['slug' => 'trainer'])
        ->assertJsonMissing(['slug' => 'super-admin']);
});

it('creates a trainer and audits the role assignment', function () {
    admin($this->tenant);

    postJson('/api/v1/admin/team', [
        'name' => 'Nita Trainer',
        'email' => 'nita@school.test',
        'password' => 'a-strong-pass-1',
        'roles' => ['trainer'],
    ])->assertCreated()
        ->assertJsonPath('data.user_type', 'staff')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.roles.0', 'trainer');

    $created = User::query()->where('email', 'nita@school.test')->firstOrFail();
    expect($created->hasRole('trainer'))->toBeTrue()
        ->and($created->tenant_id)->toBe($this->tenant->id);
    expect(AuditLog::query()->where('action', 'staff.created')->exists())->toBeTrue();
});

it('rejects assigning super-admin from the Team screen', function () {
    admin($this->tenant);

    postJson('/api/v1/admin/team', [
        'name' => 'Sneaky', 'email' => 's@school.test',
        'password' => 'a-strong-pass-1', 'roles' => ['super-admin'],
    ])->assertStatus(422)->assertJsonValidationErrorFor('roles.0');
});

it('changes a staff member roles and audits it', function () {
    admin($this->tenant);
    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');

    $res = patchJson("/api/v1/admin/team/{$trainer->id}", ['roles' => ['mentor', 'counselor']])
        ->assertOk();

    expect(collect($res->json('data.roles'))->sort()->values()->all())->toBe(['counselor', 'mentor'])
        ->and($trainer->refresh()->hasRole('trainer'))->toBeFalse();
    expect(AuditLog::query()->where('action', 'staff.roles_changed')->exists())->toBeTrue();
});

it('deactivates a staff account and blocks their sign-in', function () {
    admin($this->tenant);
    $trainer = User::factory()->for($this->tenant)->create([
        'user_type' => 'staff', 'is_active' => true, 'password' => 'a-strong-pass-1',
    ]);
    $trainer->assignRole('trainer');

    postJson("/api/v1/admin/team/{$trainer->id}/active", ['active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($trainer->refresh()->is_active)->toBeFalse();
    expect(AuditLog::query()->where('action', 'staff.deactivated')->exists())->toBeTrue();
});

it('will not let an admin deactivate their own account', function () {
    $admin = admin($this->tenant);

    postJson("/api/v1/admin/team/{$admin->id}/active", ['active' => false])
        ->assertStatus(422);
});

it('denies staff without manage-users', function () {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor'); // mentor lacks manage-users
    Sanctum::actingAs($mentor);

    getJson('/api/v1/admin/team')->assertForbidden();
    postJson('/api/v1/admin/team', [
        'name' => 'X', 'email' => 'x@school.test', 'password' => 'a-strong-pass-1', 'roles' => ['trainer'],
    ])->assertForbidden();
});

it('cannot read or manage another tenant staff (cross-tenant denial)', function () {
    admin($this->tenant);

    $other = Tenant::factory()->create();
    $foreign = User::factory()->for($other)->create(['user_type' => 'staff']);
    $foreign->assignRole('trainer');

    // The foreign user is invisible to this tenant's list…
    getJson('/api/v1/admin/team')->assertOk()->assertJsonMissing(['email' => $foreign->email]);

    // …and cannot be updated through this tenant's admin (route-model binding is tenant-scoped).
    patchJson("/api/v1/admin/team/{$foreign->id}", ['roles' => ['mentor']])->assertNotFound();
    postJson("/api/v1/admin/team/{$foreign->id}/active", ['active' => false])->assertNotFound();
});
