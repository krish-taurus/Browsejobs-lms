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
 * PRD-E F1 — remove a member from a workspace. Audited (roster change).
 * The last owner can never be removed.
 */
final readonly class RemoveEmployerMember
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(EmployerMember $member, User $actor): void
    {
        app(TenantContext::class)->run($member->tenant, function () use ($member, $actor): void {
            if ($member->role === EmployerRole::Owner) {
                $otherOwners = EmployerMember::where('employer_workspace_id', $member->employer_workspace_id)
                    ->where('role', EmployerRole::Owner->value)
                    ->whereKeyNot($member->id)
                    ->exists();

                if (! $otherOwners) {
                    throw ValidationException::withMessages([
                        'member' => 'A workspace must keep at least one owner.',
                    ]);
                }
            }

            $removedUserId = $member->user_id;
            $workspaceId = $member->employer_workspace_id;
            $member->delete();

            $this->audit->log('employer.member_removed', null, [
                'workspace_id' => $workspaceId,
                'removed_user_id' => $removedUserId,
            ], $actor);
        });
    }
}
