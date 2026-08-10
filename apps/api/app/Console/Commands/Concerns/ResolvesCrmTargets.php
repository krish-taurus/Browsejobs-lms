<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;

/**
 * Shared plumbing for the CRM-driven management commands: resolve a batch /
 * session / student across tenants (the CRM passes raw ids or numbers with no
 * tenant context of its own) and run the mutation inside the right tenant.
 */
trait ResolvesCrmTargets
{
    private function findBatch(string $identifier): ?Batch
    {
        return Batch::query()->withoutGlobalScope(TenantScope::class)
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->whereKey((int) $identifier),
                fn ($q) => $q->where('number', $identifier),
            )
            ->first();
    }

    private function findSession(string $id): ?LiveSession
    {
        return ctype_digit($id)
            ? LiveSession::query()->withoutGlobalScope(TenantScope::class)->find((int) $id)
            : null;
    }

    private function findStudent(string $identifier): ?User
    {
        return User::query()->withoutGlobalScope(TenantScope::class)
            ->where('user_type', 'student')
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->whereKey((int) $identifier),
                fn ($q) => $q->where(fn ($w) => $w->where('email', $identifier)->orWhere('phone', $identifier)),
            )
            ->first();
    }

    /**
     * @param  Closure(): int  $callback
     */
    private function runForTenant(?int $tenantId, Closure $callback): int
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $this->error('Target has no tenant.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, $callback);
    }
}
