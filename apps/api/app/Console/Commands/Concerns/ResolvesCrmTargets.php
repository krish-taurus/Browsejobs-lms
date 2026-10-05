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
        $base = fn () => User::query()->withoutGlobalScope(TenantScope::class);

        // An id comes straight from a roster the CRM has already rendered, so the
        // row exists — and a seat is not always held by a `student`: a trainer
        // taking the course, or an internal test seat, is staff. Insisting on
        // user_type=student made those rows unmanageable from the CRM, which
        // surfaced as "Student '7' not found" on a person visibly on the roster.
        if (ctype_digit($identifier)) {
            return $base()->whereKey((int) $identifier)->first();
        }

        $matches = fn ($q) => $q->where('email', $identifier)->orWhere('phone', $identifier);

        // Looking up loosely by phone or email, a real student still wins the tie
        // — staff and students can share a number in small teams.
        return $base()->where('user_type', 'student')->where($matches)->first()
            ?? $base()->where($matches)->first();
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
