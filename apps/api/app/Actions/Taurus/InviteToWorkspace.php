<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\TaurusWorkspaceRole;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceInvite;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Str;

/**
 * Invites someone into a Taurus workspace (ADR 0052). Only the sha256 of the
 * token is stored; the raw token exists once, inside the returned claim URL,
 * which the founder sends to the client himself.
 */
final class InviteToWorkspace
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{invite: TaurusWorkspaceInvite, url: string}
     */
    public function handle(TaurusWorkspace $workspace, string $email, TaurusWorkspaceRole $role, ?string $name, User $actor): array
    {
        $token = Str::random(64);

        $invite = TaurusWorkspaceInvite::query()->create([
            'tenant_id' => $workspace->tenant_id,
            'taurus_workspace_id' => $workspace->id,
            'email' => mb_strtolower(trim($email)),
            'name' => $name !== null && trim($name) !== '' ? trim($name) : null,
            'role' => $role->value,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays((int) config('taurus.invite_ttl_days', 7)),
            'invited_by' => $actor->id,
        ]);

        // The invitee's address is personal data: the audit keeps the role only.
        $this->audit->log('taurus.workspace.invited', $workspace, ['role' => $role->value], $actor);

        return ['invite' => $invite, 'url' => self::claimUrl($token)];
    }

    public static function claimUrl(string $token): string
    {
        return rtrim((string) config('app.frontend_url', ''), '/').'/taurusai/claim/'.$token;
    }
}
