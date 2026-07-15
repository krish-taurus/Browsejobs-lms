<?php

declare(strict_types=1);

use App\Actions\Auth\AssignRoleToUser;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('seeds the eight roles and their permissions', function () {
    expect(Role::query()->count())->toBe(8)
        ->and(Permission::query()->count())->toBe(12)
        ->and(Role::query()->where('slug', 'student')->value('is_staff'))->toBe(false);
});

it('reports roles and permissions for a user', function () {
    $user = User::factory()->create();
    $user->assignRole('trainer');

    expect($user->hasRole('trainer'))->toBeTrue()
        ->and($user->hasRole('admin', 'trainer'))->toBeTrue()
        ->and($user->isStaff())->toBeTrue()
        ->and($user->hasPermission('teach-classes'))->toBeTrue()
        ->and($user->hasPermission('manage-tenants'))->toBeFalse();
});

it('grants every ability to super admins via the gate', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    expect($user->can('manage-tenants'))->toBeTrue()
        ->and($user->can('anything-at-all'))->toBeTrue();
});

it('gates abilities by the permissions a role grants', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->can('manage-users'))->toBeTrue()
        ->and($admin->can('manage-tenants'))->toBeFalse();

    $student = User::factory()->create();
    $student->assignRole('student');

    expect($student->can('view-student-portal'))->toBeTrue()
        ->and($student->can('manage-users'))->toBeFalse();
});

it('assigns a role and writes an audit entry', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->for($tenant)->create();
    $target = User::factory()->for($tenant)->create();

    withinTenant($tenant, function () use ($target, $actor) {
        app(AssignRoleToUser::class)->handle($target, 'counselor', $actor);
    });

    expect($target->fresh()->hasRole('counselor'))->toBeTrue();

    $log = AuditLog::withoutGlobalScopes()->where('action', 'role.assigned')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->target_id)->toBe((string) $target->id)
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->metadata['role'])->toBe('counselor');
});

it('is idempotent and does not double-audit an already-assigned role', function () {
    $user = User::factory()->create();
    $action = app(AssignRoleToUser::class);

    $action->handle($user, 'mentor');
    $action->handle($user, 'mentor');

    expect(AuditLog::withoutGlobalScopes()->where('action', 'role.assigned')->count())->toBe(1);
});

it('scopes audit logs to their tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    AuditLog::query()->create(['tenant_id' => $tenantA->id, 'action' => 'a.thing']);
    AuditLog::query()->create(['tenant_id' => $tenantB->id, 'action' => 'b.thing']);

    withinTenant($tenantA, function () {
        expect(AuditLog::query()->pluck('action')->all())
            ->toContain('a.thing')
            ->not->toContain('b.thing');
    });
});
