<?php

declare(strict_types=1);

namespace App\Support\Taurus;

use App\Models\TaurusWorkspace;
use Illuminate\Support\Carbon;

/**
 * The two public shapes of a Taurus workspace (ADR 0052). Neither carries
 * keys, token hashes or agent data.
 */
final class WorkspacePresenter
{
    /**
     * What a member sees in their workspace switcher.
     *
     * @return array{id: int, name: string, slug: string, status: string, is_owner: bool, role: string}
     */
    public static function forMember(TaurusWorkspace $workspace, string $role): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'status' => $workspace->status,
            'is_owner' => $workspace->is_owner,
            'role' => $role,
        ];
    }

    /**
     * What the founder sees in the workspace list. Expects members_count,
     * agents_count and agents_max_last_seen_at loaded (see WorkspaceAdminController).
     *
     * @return array<string, mixed>
     */
    public static function forAdmin(TaurusWorkspace $workspace): array
    {
        $lastSeen = $workspace->getAttribute('agents_max_last_seen_at');

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'status' => $workspace->status,
            'is_owner' => $workspace->is_owner,
            'members_count' => (int) ($workspace->getAttribute('members_count') ?? 0),
            'agents_count' => (int) ($workspace->getAttribute('agents_count') ?? 0),
            'last_activity_at' => $lastSeen !== null ? Carbon::parse((string) $lastSeen)->toIso8601String() : null,
            'created_at' => $workspace->created_at->toIso8601String(),
        ];
    }
}
