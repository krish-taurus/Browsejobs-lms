<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Assigns a role to a user and records the change in the audit trail.
 * Role changes are one of the mandatory audit events in CLAUDE.md.
 */
final readonly class AssignRoleToUser
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, Role|string $role, ?User $actor = null): void
    {
        $role = $role instanceof Role
            ? $role
            : Role::query()->where('slug', $role)->firstOrFail();

        if ($user->hasRole($role->slug)) {
            return;
        }

        $user->assignRole($role);

        $this->audit->log(
            action: 'role.assigned',
            target: $user,
            metadata: ['role' => $role->slug],
            actor: $actor,
        );
    }
}
