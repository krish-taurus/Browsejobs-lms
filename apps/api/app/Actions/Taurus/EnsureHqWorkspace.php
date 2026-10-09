<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Enums\TaurusWorkspaceRole;
use App\Models\TaurusWorkspace;
use App\Models\TaurusWorkspaceMember;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Makes sure the founder's own workspace, "Taurus HQ", exists (ADR 0052) and
 * that the calling owner is in it. Idempotent — the console calls it on every
 * first visit. One owner workspace per tenant.
 */
final class EnsureHqWorkspace
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $owner): TaurusWorkspace
    {
        return DB::transaction(function () use ($owner): TaurusWorkspace {
            $workspace = TaurusWorkspace::query()->where('is_owner', true)->lockForUpdate()->first();

            if ($workspace === null) {
                $workspace = TaurusWorkspace::query()->create([
                    'tenant_id' => $owner->tenant_id,
                    'name' => 'Taurus HQ',
                    'slug' => CreateClientWorkspace::uniqueSlug('taurus-hq'),
                    'status' => TaurusWorkspace::STATUS_ACTIVE,
                    'is_owner' => true,
                    // HQ starts on the platform's AI; it may add its own keys later.
                    'brain_provider' => 'platform',
                    'created_by' => $owner->id,
                ]);

                $this->audit->log('taurus.workspace.created', $workspace, ['name' => $workspace->name, 'hq' => true], $owner);
            }

            TaurusWorkspaceMember::query()->firstOrCreate(
                ['taurus_workspace_id' => $workspace->id, 'user_id' => $owner->id],
                ['tenant_id' => $workspace->tenant_id, 'role' => TaurusWorkspaceRole::Owner->value],
            );

            return $workspace;
        });
    }
}
