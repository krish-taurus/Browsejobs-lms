<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerRole;
use App\Models\EmployerMember;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F1 — change a member's workspace role. Audited (CLAUDE.md:
 * role changes require an audit entry). A workspace must always retain
 * at least one Owner.
 */
final readonly class ChangeEmployerMemberRole
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(EmployerMember $member, EmployerRole $role, User $actor): EmployerMember
    {
        return app(TenantContext::class)->run($member->tenant, function () use ($member, $role, $actor): EmployerMember {
            if ($member->role === $role) {
                return $member;
            }

            $this->guardLastOwner($member);

            $previous = $member->role;
            $member->update(['role' => $role->value]);

            $this->audit->log('employer.member_role_changed', $member, [
                'workspace_id' => $member->employer_workspace_id,
                'from' => $previous->value,
                'to' => $role->value,
            ], $actor);

            return $member->fresh();
        });
    }

    private function guardLastOwner(EmployerMember $member): void
    {
        if ($member->role !== EmployerRole::Owner) {
            return;
        }

        $otherOwners = EmployerMember::where('employer_workspace_id', $member->employer_workspace_id)
            ->where('role', EmployerRole::Owner->value)
            ->whereKeyNot($member->id)
            ->exists();

        if (! $otherOwners) {
            throw ValidationException::withMessages([
                'role' => 'A workspace must keep at least one owner.',
            ]);
        }
    }
}
