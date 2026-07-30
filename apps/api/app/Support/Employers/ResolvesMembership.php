<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerMember;
use App\Models\EmployerWorkspace;
use App\Models\User;

/**
 * Workspace-scoped authorization for employer controllers (ADR 0051).
 *
 * Employer roles are membership attributes, not platform Role rows, so
 * route-level `can:` gates don't apply here — controllers resolve the
 * caller's membership and 403/404 accordingly. Tenant isolation is
 * already enforced upstream by TenantScope + tenant.user middleware.
 */
trait ResolvesMembership
{
    protected function membershipOrFail(EmployerWorkspace $workspace, User $user): EmployerMember
    {
        $member = $workspace->memberFor($user);

        abort_if($member === null, 403, 'You are not a member of this workspace.');

        return $member;
    }

    protected function ownerOrFail(EmployerWorkspace $workspace, User $user): EmployerMember
    {
        $member = $this->membershipOrFail($workspace, $user);

        abort_unless($member->role->managesWorkspace(), 403, 'Only a workspace owner can do this.');

        return $member;
    }
}
