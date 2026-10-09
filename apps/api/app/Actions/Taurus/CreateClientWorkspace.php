<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\TaurusWorkspaceRole;
use App\Models\TaurusWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The founder opens a Taurus workspace for a client business (ADR 0052) and
 * gets back an owner invite to hand over. The workspace starts empty: no
 * agents, no token, no keys — the client brings their own.
 */
final class CreateClientWorkspace
{
    public function __construct(
        private readonly InviteToWorkspace $invite,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{workspace: TaurusWorkspace, invite_url: string}
     */
    public function handle(string $name, string $ownerEmail, ?string $ownerName, User $actor): array
    {
        return DB::transaction(function () use ($name, $ownerEmail, $ownerName, $actor): array {
            $workspace = TaurusWorkspace::query()->create([
                'tenant_id' => $actor->tenant_id,
                'name' => trim($name),
                'slug' => self::uniqueSlug($name),
                'status' => TaurusWorkspace::STATUS_ACTIVE,
                'is_owner' => false,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('taurus.workspace.created', $workspace, ['name' => $workspace->name], $actor);

            $invite = $this->invite->handle($workspace, $ownerEmail, TaurusWorkspaceRole::Owner, $ownerName, $actor);

            return ['workspace' => $workspace, 'invite_url' => $invite['url']];
        });
    }

    /** A per-tenant unique slug from the name (the tenant scope confines the check). */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'workspace', 56, '');
        $slug = $base;
        $n = 2;

        while (TaurusWorkspace::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
