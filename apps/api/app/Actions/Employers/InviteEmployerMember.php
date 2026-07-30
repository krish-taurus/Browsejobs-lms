<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerRole;
use App\Events\EmployerMemberInvited;
use App\Models\EmployerInvite;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F1 — invite a teammate into a workspace by email.
 *
 * Token rules (CLAUDE.md): random, single-use, expiring; consumed
 * atomically in AcceptEmployerInvite.
 */
final readonly class InviteEmployerMember
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(EmployerWorkspace $workspace, User $inviter, string $email, EmployerRole $role): EmployerInvite
    {
        return app(TenantContext::class)->run($workspace->tenant, function () use ($workspace, $inviter, $email, $role): EmployerInvite {
            $email = Str::lower(trim($email));

            $alreadyMember = $workspace->members()
                ->whereHas('user', fn ($query) => $query->where('email', $email))
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => 'This person is already a member of the workspace.',
                ]);
            }

            $pendingExists = $workspace->invites()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'email' => 'An invite for this email is already pending.',
                ]);
            }

            $invite = EmployerInvite::create([
                'employer_workspace_id' => $workspace->id,
                'invited_by_id' => $inviter->id,
                'email' => $email,
                'role' => $role->value,
                'token' => Str::random(64),
                'expires_at' => now()->addDays((int) config('employers.invite_ttl_days')),
            ]);

            $this->audit->log('employer.member_invited', $invite, [
                'workspace_id' => $workspace->id,
                'email' => $email,
                'role' => $role->value,
            ], $inviter);

            EmployerMemberInvited::dispatch($invite);

            return $invite;
        });
    }
}
