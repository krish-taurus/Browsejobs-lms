<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use App\Models\TaurusWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Forgets one of a workspace's own keys (an LLM provider's, or `elevenlabs`).
 * There is nothing to fall back to for a client workspace — that is the point.
 */
final class ClearWorkspaceCredential
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(TaurusWorkspace $workspace, string $provider, User $actor): void
    {
        $deleted = $workspace->credentials()->where('provider', $provider)->delete();

        if ($deleted > 0) {
            $this->audit->log('taurus.credential.cleared', $workspace, ['provider' => $provider], $actor);
        }
    }
}
