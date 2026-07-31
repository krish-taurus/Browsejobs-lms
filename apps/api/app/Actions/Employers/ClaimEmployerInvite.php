<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerInvite;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Claim an onboarding invite: set the account's first password and take up
 * the workspace membership (PRD-E F1).
 *
 * This is the other half of admin-led onboarding. Ops creates the workspace
 * and the owner's account with no usable password; the employer follows the
 * emailed link and chooses their own credential here. Nobody at BrowseJobs
 * ever knows it.
 *
 * The guards, in the order they matter:
 *
 *  - The token must carry `grants_credential`, which the onboarding action
 *    sets only when it created the account. A plain team invite reaching an
 *    address that happens to match an existing user must never be able to
 *    overwrite that user's password.
 *  - The account must still be unclaimed. Once a password has been set the
 *    link is spent, so a leaked email thread cannot be replayed later.
 *  - Consumption is a guarded UPDATE, so two concurrent claims cannot both
 *    succeed.
 */
final readonly class ClaimEmployerInvite
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(string $token, string $password): User
    {
        $invite = EmployerInvite::withoutGlobalScopes()->where('token', $token)->first();

        if ($invite === null || ! $invite->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'This invite is invalid or has expired.',
            ]);
        }

        if (! $invite->grants_credential) {
            throw ValidationException::withMessages([
                'token' => 'This invite does not set a password. Sign in with your existing account to accept it.',
            ]);
        }

        $user = User::withoutGlobalScopes()
            ->where('email', $invite->email)
            ->where('tenant_id', $invite->tenant_id)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => 'This invite is invalid or has expired.',
            ]);
        }

        return app(TenantContext::class)->run($user->tenant, function () use ($invite, $user, $password): User {
            return DB::transaction(function () use ($invite, $user, $password): User {
                // Atomic consume: only one claim can flip accepted_at, so a
                // replayed request finds nothing left to spend.
                $consumed = EmployerInvite::withoutGlobalScopes()
                    ->whereKey($invite->id)
                    ->whereNull('accepted_at')
                    ->update(['accepted_at' => now()]);

                if ($consumed === 0) {
                    throw ValidationException::withMessages([
                        'token' => 'This invite has already been used.',
                    ]);
                }

                $user->forceFill([
                    'password' => $password, // hashed by the model cast
                    'user_type' => $user->user_type === 'staff' ? 'staff' : 'employer',
                    'is_active' => true,
                ])->save();

                $this->audit->log('employer.invite_claimed', $user, [
                    'workspace_id' => $invite->employer_workspace_id,
                    'email' => $invite->email,
                ], $user);

                return $user->fresh();
            });
        });
    }
}
