<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Rename or suspend/reactivate a workspace (ADR 0052). Suspension stops the
 * console and the bots' ingest at once. HQ can never be suspended.
 */
final class UpdateWorkspace
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CannotSuspendHq
     */
    public function handle(TaurusWorkspace $workspace, ?string $status, ?string $name, User $actor): TaurusWorkspace
    {
        if ($status === TaurusWorkspace::STATUS_SUSPENDED && $workspace->is_owner) {
            throw new CannotSuspendHq;
        }

        $changes = [];
        if ($status !== null && $status !== $workspace->status) {
            $changes['status'] = $status;
        }
        if ($name !== null && trim($name) !== '' && trim($name) !== $workspace->name) {
            $changes['name'] = trim($name);
        }

        if ($changes !== []) {
            $workspace->fill($changes)->save();
            $this->audit->log('taurus.workspace.updated', $workspace, $changes, $actor);
        }

        return $workspace;
    }
}
