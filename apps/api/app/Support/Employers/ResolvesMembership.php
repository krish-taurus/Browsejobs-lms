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

        // Suspension is enforced here because this is the one place every
        // employer endpoint passes through. It was previously a column
        // nothing read, which made "suspend" a button that changed a label
        // and nothing else — the whole team kept full access to candidate
        // contact details and the pipeline.
        abort_if(
            $workspace->status === EmployerWorkspace::STATUS_SUSPENDED,
            403,
            'This workspace is suspended. Contact BrowseJobs to restore access.',
        );

        return $member;
    }

    protected function ownerOrFail(EmployerWorkspace $workspace, User $user): EmployerMember
    {
        $member = $this->membershipOrFail($workspace, $user);

        abort_unless($member->role->managesWorkspace(), 403, 'Only a workspace owner can do this.');

        return $member;
    }
}
