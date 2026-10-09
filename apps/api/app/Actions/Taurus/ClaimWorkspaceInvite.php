<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\Scopes\TenantScope;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceInvite;
use App\Models\TaurusWorkspaceMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A client accepts a Taurus workspace invite (ADR 0052).
 *
 * New email → a `client` user is created with the chosen password. An email
 * that already has an account in this tenant joins only when the password
 * matches that account — an invite link alone never takes over an existing
 * login. The invite is consumed atomically, so a link works exactly once.
 */
final class ClaimWorkspaceInvite
{
    private const INVALID = 'This invite is invalid or has expired.';

    /**
     * @return array{user: User, workspace: TaurusWorkspace, member: TaurusWorkspaceMember}
     */
    public function handle(Tenant $tenant, string $token, string $name, string $password): array
    {
        $invite = TaurusWorkspaceInvite::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $workspace = $invite !== null
            ? TaurusWorkspace::withoutGlobalScopes()->whereKey($invite->taurus_workspace_id)->first()
            : null;

        if ($invite === null || $workspace === null
            || $invite->accepted_at !== null
            || $invite->expires_at->isPast()
            || ! $workspace->isActive()) {
            throw ValidationException::withMessages(['token' => self::INVALID]);
        }

        $existing = User::query()->withoutGlobalScope(TenantScope::class)->where('email', $invite->email)->first();

        if ($existing !== null) {
            $sameTenant = (int) $existing->tenant_id === (int) $tenant->id;
            $passwordOk = $existing->password !== null && Hash::check($password, $existing->password);

            if (! $sameTenant || ! $passwordOk || ! $existing->is_active) {
                throw ValidationException::withMessages([
                    'password' => 'An account already uses this email. Enter that account\'s password to join.',
                ]);
            }
        }

        return DB::transaction(function () use ($invite, $workspace, $existing, $tenant, $name, $password): array {
            // Atomic consume: only one claim can flip accepted_at.
            $consumed = TaurusWorkspaceInvite::withoutGlobalScopes()
                ->whereKey($invite->id)
                ->whereNull('accepted_at')
                ->update(['accepted_at' => now()]);

            if ($consumed !== 1) {
                throw ValidationException::withMessages(['token' => 'This invite has already been used.']);
            }

            $user = $existing ?? User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => trim($name),
                'email' => $invite->email,
                'user_type' => 'client',
                'is_active' => true,
                'password' => $password, // hashed by the model cast
            ]);

            $member = TaurusWorkspaceMember::withoutGlobalScopes()->firstOrCreate(
                ['taurus_workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['tenant_id' => $tenant->id, 'role' => $invite->role->value],
            );

            return ['user' => $user, 'workspace' => $workspace, 'member' => $member];
        });
    }
}
