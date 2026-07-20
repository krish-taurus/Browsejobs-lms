<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Auth\StaffLogin;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Activate or deactivate a staff account. A deactivated user is blocked at
 * staff sign-in ({@see StaffLogin}). Access blocks/unblocks
 * are audited (CLAUDE.md).
 */
final readonly class SetStaffActive
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, bool $active): User
    {
        $user->update(['is_active' => $active]);
        $this->audit->log($active ? 'staff.reactivated' : 'staff.deactivated', $user);

        return $user->load('roles');
    }
}
