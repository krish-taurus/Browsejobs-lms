<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the eight platform roles (PRD §4) and their permissions. Idempotent:
 * safe to re-run. Super Admin is granted every permission for clarity, though
 * the Gate::before hook already bypasses gates for that role.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'manage-tenants' => 'Provision and configure tenants',
        'manage-users' => 'Create and manage user accounts',
        'manage-roles' => 'Assign roles and permissions',
        'manage-curriculum' => 'Manage programs, courses, modules, topics, lessons',
        'manage-batches' => 'Create and manage batches',
        'manage-rosters' => 'Manage batch rosters',
        'teach-classes' => 'Run live classes and mark attendance',
        'mentor-students' => 'Mentor students and take bookings',
        'manage-leads' => 'Work the CRM lead pipeline',
        'manage-fees' => 'Manage fee plans, payments and receipts',
        'manage-placements' => 'Manage placement pipeline and jobs',
        'handle-tickets' => 'Work the student support desk',
        'view-student-portal' => 'Access the student learning portal',
    ];

    /**
     * @var array<string, array{name: string, is_staff: bool, permissions: list<string>|string}>
     */
    private const ROLES = [
        'super-admin' => ['name' => 'Super Admin', 'is_staff' => true, 'permissions' => '*'],
        'admin' => ['name' => 'Admin (Institute)', 'is_staff' => true, 'permissions' => [
            'manage-users', 'manage-roles', 'manage-curriculum', 'manage-batches',
            'manage-rosters', 'manage-leads', 'manage-fees', 'manage-placements', 'handle-tickets',
        ]],
        'trainer' => ['name' => 'Trainer', 'is_staff' => true, 'permissions' => [
            'teach-classes', 'manage-curriculum', 'manage-rosters',
        ]],
        'mentor' => ['name' => 'Mentor', 'is_staff' => true, 'permissions' => ['mentor-students']],
        'counselor' => ['name' => 'Counselor', 'is_staff' => true, 'permissions' => ['manage-leads']],
        'placement-officer' => ['name' => 'Placement Officer', 'is_staff' => true, 'permissions' => ['manage-placements']],
        'support-agent' => ['name' => 'Support Agent', 'is_staff' => true, 'permissions' => ['handle-tickets']],
        'student' => ['name' => 'Student', 'is_staff' => false, 'permissions' => ['view-student-portal']],
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->mapWithKeys(function (string $description, string $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->headline()->toString(), 'description' => $description],
            );

            return [$slug => $permission->id];
        });

        foreach (self::ROLES as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'is_staff' => $definition['is_staff']],
            );

            $grant = $definition['permissions'] === '*'
                ? $permissions->values()->all()
                : $permissions->only($definition['permissions'])->values()->all();

            $role->permissions()->sync($grant);
        }
    }
}
