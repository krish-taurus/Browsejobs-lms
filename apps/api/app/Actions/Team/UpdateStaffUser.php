<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Update a staff account: name/phone, an optional password reset, and the set
 * of assigned roles. Role changes are audited (CLAUDE.md).
 *
 * @phpstan-type StaffUpdate array{name?: string, phone?: string|null, password?: string|null, roles?: list<string>}
 */
final readonly class UpdateStaffUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  StaffUpdate  $data
     */
    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if (array_key_exists('name', $data)) {
                $user->name = $data['name'];
            }
            if (array_key_exists('phone', $data)) {
                $user->phone = $data['phone'];
            }
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            if (array_key_exists('roles', $data)) {
                $before = $user->roles()->pluck('slug')->sort()->values()->all();
                $roleIds = Role::query()->whereIn('slug', $data['roles'])->pluck('id');
                $user->roles()->sync($roleIds);
                $after = array_values($data['roles']);

                if ($before !== collect($after)->sort()->values()->all()) {
                    $this->audit->log('staff.roles_changed', $user, ['from' => $before, 'to' => $after]);
                }
            }

            return $user->load('roles');
        });
    }
}
