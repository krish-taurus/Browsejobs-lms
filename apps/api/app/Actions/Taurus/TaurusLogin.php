<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\Scopes\TenantScope;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in for Taurus clients (ADR 0052): email + password within the host
 * tenant, and only for someone who belongs to at least one active workspace.
 * Every refusal is the same generic message, so the form never confirms
 * whether an email has an account.
 *
 * Accounts with two-factor sign-in (staff) are refused here: they use the
 * staff sign-in, which enforces the second factor.
 */
final class TaurusLogin
{
    public const GENERIC = 'Those details don\'t match a Taurus workspace.';

    public function handle(Tenant $tenant, string $email, string $password): User
    {
        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', trim($email))
            ->first();

        $ok = $user !== null
            && $user->password !== null
            && Hash::check($password, $user->password)
            && $user->is_active
            && ! $user->two_factor_enabled
            && TaurusWorkspaceMember::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereIn('taurus_workspace_id', TaurusWorkspace::withoutGlobalScopes()
                    ->select('id')
                    ->where('tenant_id', $tenant->id)
                    ->where('status', TaurusWorkspace::STATUS_ACTIVE))
                ->exists();

        if (! $ok) {
            throw ValidationException::withMessages(['email' => self::GENERIC]);
        }

        return $user;
    }
}
