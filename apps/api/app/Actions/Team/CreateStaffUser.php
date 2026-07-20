<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Create a staff account (trainer, mentor, counselor, …) inside a tenant and
 * assign its roles. Role changes are audited (CLAUDE.md). The password is
 * hashed by the model's `hashed` cast.
 *
 * @phpstan-type StaffInput array{name: string, email: string, phone?: string|null, password: string, roles: list<string>}
 */
final readonly class CreateStaffUser
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  StaffInput  $data
     */
    public function handle(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data): User {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'user_type' => 'staff',
                'is_active' => true,
                'password' => $data['password'],
            ]);

            $roleIds = Role::query()->whereIn('slug', $data['roles'])->pluck('id');
            $user->roles()->sync($roleIds);

            $this->audit->log('staff.created', $user, ['roles' => array_values($data['roles'])]);

            return $user->load('roles');
        });
    }
}
