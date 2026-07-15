<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Central writer for the audit trail. CLAUDE.md requires an audit entry for
 * grade changes, fee waivers, access blocks, roster changes, role changes,
 * and ticket escalations — every such site should call this.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?Model $target = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();
        $tenantId = app(TenantContext::class)->id() ?? $actor?->tenant_id;

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'target_type' => $target !== null ? $target::class : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
