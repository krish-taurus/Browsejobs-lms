<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Events\EmployerMemberJoined;
use App\Models\EmployerInvite;
use App\Models\EmployerMember;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F1 — consume an invite token and enrol the user.
 *
 * The token is consumed atomically: the guarded UPDATE on accepted_at is
 * the lock, so two concurrent accepts cannot both succeed.
 */
final readonly class AcceptEmployerInvite
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, string $token): EmployerMember
    {
        $invite = EmployerInvite::withoutGlobalScopes()->where('token', $token)->first();

        if ($invite === null || ! $invite->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'This invite is invalid or has expired.',
            ]);
        }

        if (! hash_equals($invite->email, mb_strtolower($user->email))) {
            throw ValidationException::withMessages([
                'token' => 'This invite was issued to a different email address.',
            ]);
        }

        if ($invite->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'token' => 'This invite is invalid or has expired.',
            ]);
        }

        return app(TenantContext::class)->run($user->tenant, function () use ($invite, $user): EmployerMember {
            return DB::transaction(function () use ($invite, $user): EmployerMember {
                // Atomic consume: only one accept can flip accepted_at.
                $consumed = EmployerInvite::withoutGlobalScopes()
                    ->whereKey($invite->id)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->update(['accepted_at' => now()]);

                if ($consumed === 0) {
                    throw ValidationException::withMessages([
                        'token' => 'This invite is invalid or has expired.',
                    ]);
                }

                $member = EmployerMember::create([
                    'employer_workspace_id' => $invite->employer_workspace_id,
                    'user_id' => $user->id,
                    'role' => $invite->role->value,
                    'joined_at' => now(),
                ]);

                if ($user->user_type !== 'employer' && $user->user_type !== 'staff') {
                    $user->forceFill(['user_type' => 'employer'])->save();
                }

                $this->audit->log('employer.member_joined', $member, [
                    'workspace_id' => $invite->employer_workspace_id,
                    'role' => $invite->role->value,
                ], $user);

                EmployerMemberJoined::dispatch($member);

                return $member;
            });
        });
    }
}
